<?php

class Issue extends GitBase {

  static $inDevProjectsFile;

  protected $projectGitFolders;

  /*
  protected function checkIsClean($project) {
    if (!(new GitFolder($this->getGitProjectFolder($project)))->isClean()) {
      if (Cli::confirm("U have changes in current branch. Would U switch that changes to 'i-$id' branch?")) {
        return true;
      }
    }
    return false;
  }
  */

  protected function projects() {
    return new FileItems(__DIR__.'/data/projects.php');
  }

  protected function getGitProjectFolder($project) {
    $this->initProjectGitFolders();
    if (!isset($this->projectGitFolders[$project])) throw new EmptyException("Project '$project' does not exist");
    return Misc::checkEmpty($this->projectGitFolders[$project]);
  }

  protected function notCleanProjects(array $projects) {
    return array_filter($projects, function ($project) {
      return !(new GitFolder($this->getGitProjectFolder($project)))->isClean();
    });
  }

  /**
   * Отображает все открытые задачи
   */
  function opened() {
    $r = $this->getIssueBranches();
    if (!$r) {
      print "No opened issues\n";
      return;
    }
    foreach ($r as $id => $projects) {
      if (!($issue = $this->getIssue($id, false))) {
        output("No issue data for ID '$id'. Need to cleanup");
        return;
      }
      print "$id: ".implode(', ', $projects)." -- ".$this->projects()->getItem($id)['title']."\n";
//
    }
  }

  /**
   * Создаёт новую ветку для работы над задачей
   */
  function create($title, $project, $depProjects = '', $masterBranch = 'master', $depProjectsMasterBranch = 'master') {
    $title = str_replace('_', ' ', $title);
    $projects = $this->projects();
    $id = $projects->create(['title' => $title]);
    //die2($id);
    try {
      $this->_create($id, $project, $depProjects, $masterBranch, $depProjectsMasterBranch);
    } catch (Exception $e) {
      $projects->delete($id);
      throw $e;
    }
    // проверяем. если ветки нет, удаляем запись
  }

  protected function _create($id, $project, $depProjects = '', $masterBranch = 'master', $depProjectsMasterBranch = 'master') {
    $this->cleanupInDev();
    $depProjects = Misc::quoted2arr($depProjects);
    $projects = array_merge([$project], $depProjects);
    $notCleanProjects = $this->notCleanProjects($projects);
    $issueBranches = $this->getIssueBranches();
    if (isset($issueBranches[$id])) throw new Exception("Issue $id already started in projects: ".implode(', ', $issueBranches[$id]));
    if ($notCleanProjects and !Cli::confirm("Would U like to checkout dirty project changes to new 'i-$id' branch?\nDirty projects: ".implode(', ', $notCleanProjects))) return;
    foreach ($projects as $p) {
      $issueFolder = new IssueGitFolder($this->getGitProjectFolder($p));
      $masterBranch = $p == $project ? 'master' : $depProjectsMasterBranch;
      if (in_array($p, $notCleanProjects)) {
        $issueFolder->createNotClean($id, $masterBranch);
      }
      else {
        $issueFolder->create($id, $masterBranch);
      }
      $issueFolder->shellexec("git push ".self::$remote." i-$id");
    }
    FileVar::updateSubVar(self::$inDevProjectsFile, $id, [
      'project'                       => $project,
      'masterBranch'                  => $masterBranch,
      'dependingProjectsMasterBranch' => $depProjectsMasterBranch
    ]);
  }

  /**
   * Создаёт новую ветку для работы над задачей для всех проектов, которые были изменены
   */
  function refactor($id) {
    foreach ($this->findGitFolders() as $folder) {
      $git = new GitFolder($folder);
      if (!$git->isClean()) {
        //$git->
      }
    }
  }

  /**
   * Переключает указанный проект на ветку с задачей
   */
  function add($id, $depProject, $masterBranch = 'master') {
    Misc::checkNumeric($id);
    $this->cleanupInDev();
    $isClean = !(new GitFolder($this->getGitProjectFolder($depProject)))->isClean();
    $issueFolder = new IssueGitFolder($this->getGitProjectFolder($depProject));
    if ($isClean) {
      $issueFolder->create($id, $masterBranch);
    } else {
      $issueFolder->createNotClean($id, $masterBranch);
    }
  }

  protected function makeIssueBranch($id, $project) {
    output("Creating i-$id branch in '{$this->projectGitFolders[$project]}' folder, '$project' project");
    chdir($this->projectGitFolders[$project]);
    print `git checkout -b i-$id`;
  }

  /*
  function pull($id) {
    $this->getDependingProjects($id);
    return;
    foreach ($this->getDependingProjects($id) as $project) {
      chdir($this->projectGitFolders[$project]);
      `git pull origin {$inDevProject['dependingProjectsMasterBranch']}`;
    }
  }
  */

  protected function getDependingProjects($id) {
    $issueBranches = $this->getIssueBranches();
    if (!isset($issueBranches[$id])) throw new Exception("No depending projects for issue $id");
    return Arr::drop($issueBranches[$id], self::getIssue($id)['project']);
  }

  protected function initProjectGitFolders() {
    if (isset($this->projectGitFolders)) return;
    $this->projectGitFolders = [];
    foreach ($this->findGitFolders() as $f) $this->projectGitFolders[basename($f)] = $f;
  }

  /**
   * Мёрджит изменения в мастер-ветку и удаляет ветку с задачей
   */
  function complete($id) {
    $this->cleanupInDev();
    $this->close($id, 'complete');
  }

  /**
   * Удаляет ветку с задачей
   */
  function delete($id) {
    $this->cleanupInDev();
    $this->close($id, 'delete');
    $this->projects()->delete($id);
  }

  protected function close($id, $method) {
    $issue = self::getIssue($id);
    $issueBranches = $this->getIssueBranches(self::returnFolder);
    if (!isset($issueBranches[$id])) {
      output("No issue branches by ID $id");
      return;
    }
    foreach ($issueBranches[$id] as $f) {
      (new IssueGitFolder($f))->$method($id, self::projectBranch(basename($f), $issue));
    }
    FileVar::removeSubVar(self::$inDevProjectsFile, $id);
  }

  static function getIssue($id, $strict = true) {
    $r = FileVar::getVar(self::$inDevProjectsFile);
    if (!isset($r[$id])) {
      if ($strict) throw new EmptyException("No issue with ID '$id'");
      else return false;
    }
    return $r[$id];
  }

  static function projectBranch($project, array $issue) {
    if ($project == $issue['project']) {
      return $issue['masterBranch'];
    } else {
      if (empty($issue['dependingProjectsMasterBranch'])) throw new EmptyException('dependingProjectsMasterBranch');
      return $issue['dependingProjectsMasterBranch'];
    }
  }

  /*
  protected function release() {
    if (`ci test`) {
      `git checkout master`;
      `git merge i-123`;
      `git push origin master`;
      'prod ci update';
    }
  }
  */

  const returnFolder = 1, returnProject = 2;

  protected function getIssueBranches($return = self::returnProject) {
    $r = [];
    foreach ($this->findGitFolders() as $f) {
      chdir($f);
      $branches = `git branch`;
      foreach (explode("\n", $branches) as $name) {
        $name = trim(Misc::removePrefix('* ', $name));
        if (Misc::hasPrefix('i-', $name)) $r[Misc::removePrefix('i-', $name)][] = $return == self::returnProject ? basename($f) : $f;
      }
    }
    return $r;
  }

  /**
   * Показывает статус задачи
   */
  function status($id) {
    $issueBranches = $this->getIssueBranches(self::returnFolder);
    $remote = self::$remote;
    $clean = true;
    foreach ($issueBranches[$id] as $f) {
      chdir($f);
      $r = `git remote show $remote`;
      $p = basename($f);
      if (!preg_match("/i-$id\\s+pushes to i-$id\\s+\\((.*)\\)/", $r, $m)) throw new Exception("No remote for project '$p' branch '$id' or you didn't make first push");
      if ($m[1] != 'up to date') {
        $clean = false;
        print basename($f).': '.$m[1]."\n";
      }
    }
    if (!$clean) print "Need to update\n";
    else print "Everything is clean";
    return $clean;
  }

  /**
   * Обновляет ветку с задачей из ремоута
   */
  function update($id) {
    $issueBranches = $this->getIssueBranches(self::returnFolder);
    if (!isset($issueBranches[$id])) {
      output("No issue branches by ID $id");
      return;
    }
    $remote = self::$remote;
    foreach ($issueBranches[$id] as $f) {
      chdir($f);
      print `git add .`;
      print `git commit`;
      sys("git push $remote i-$id", true);
      //print `git push $remote i-$id`;
    }
  }

  /**
   * Синхронизирует файл с данными об открытых ветках с реалиями
   */
  function cleanupInDev() {
    foreach ($this->getIssueBranches() as $id => $projects) {
      foreach ($projects as $project) {
        FileVar::updateSubVar(self::$inDevProjectsFile, $id, [
          'project'                       => $project,
          'masterBranch'                  => 'master',
          'dependingProjectsMasterBranch' => null
        ]);
      }
    }
  }

  // --

  static $remote;

  //}

  /*
  function test($id) {
    $issueBranches = $this->getIssueBranches(self::returnFolder);
    if (!isset($issueBranches[$id])) {
      output("No issue branches by ID $id");
      return;
    }
    foreach ($issueBranches[$id] as $f) {
      (new IssueGitFolder($f))->push($id);
    }
    $host = self::$remoteTestServer[0]['host'];
    $port = self::$remoteTestServer[0]['port'];
    print `ssh user@$host -p $port ci update`;
  }
  */

}

Issue::$inDevProjectsFile = __DIR__.'/.projectDependingBranches.php';
Issue::$remote = file_exists(__DIR__.'/.remote') ? trim(file_get_contents(__DIR__.'/.remote')) : 'origin';
FileVar::touch(Issue::$inDevProjectsFile);