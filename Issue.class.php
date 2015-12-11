<?php

class Issue extends GitBase {

  static $inDevProjectsFile;

  protected $projectGitFolders;

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
    $r = (new IssueBranchFolders)->getLocal();
    if (!$r) {
      print "No opened issues\n";
      return;
    }
    foreach ($r as $id => $projects) {
      if (!($issue = $this->getIssue($id, false))) {
        output("No issue data for ID '$id'. Need to cleanup");
        return;
      }
      print "$id: ".implode(', ', $projects)." -- ".(new IssueRecords)->getItem($id)['title']."\n";
//
    }
  }

  /**
   * Создаёт новую ветку для работы над задачей
   */
  function create($title, $project, $depProjects = '', $masterBranch = 'master', $depProjectsMasterBranch = 'master') {
    $title = str_replace('_', ' ', $title);
    $projects = new IssueRecords;
    $id = $projects->create(['title' => $title]);
    try {
      $this->_create($id, $project, $depProjects, $masterBranch, $depProjectsMasterBranch);
    } catch (Exception $e) {
      $projects->delete($id);
      throw $e;
    }
    $localBranches = (new GitFolder($this->getGitProjectFolder($project)))->localBranches();
    if (!in_array("i-$id", $localBranches)) {
      $projects->delete($id);
    }
  }

  protected function _create($id, $project, $depProjects = '', $masterBranch = 'master', $depProjectsMasterBranch = 'master') {
    $this->cleanupInDev();
    $depProjects = Misc::quoted2arr($depProjects);
    $projects = array_merge([$project], $depProjects);
    $notCleanProjects = $this->notCleanProjects($projects);
    $issueBranches = (new IssueBranchFolders)->getLocal();
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

  /**
   * Выкачивает ветки с ремоута
   */
  protected function fetch() {
    foreach ($this->findGitFolders() as $f) {
      chdir($f);
      output2("fetch $f");
      print `git fetch`;
    }
  }

  protected function checkoutFromRemote($id) {
    $b = $id == 'master' ? 'master' : "i-$id";
    foreach ($this->findGitFolders() as $f) {
      chdir($f);
      $remoteBranches = `git branch -r`;
      $remoteBranches = explode("\n", trim($remoteBranches));
      $remoteBranches = array_map('trim', $remoteBranches);
      foreach ($remoteBranches as $branch) {
        if ($branch == 'origin/'.$b) {
          output2('checkout '.$b.' in '.basename($f));
          print `git reset --hard origin/master`;
          print `git checkout $b`;
        }
      }
    }
  }

  function checkout($id) {
    //$this->fetch();
    $this->checkoutFromRemote($id);
  }

  protected function makeIssueBranch($id, $project) {
    output("Creating i-$id branch in '{$this->projectGitFolders[$project]}' folder, '$project' project");
    chdir($this->projectGitFolders[$project]);
    print `git checkout -b i-$id`;
  }

  protected function getDependingProjects($id) {
    $issueBranches = (new IssueBranchFolders)->getLocal();
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
    (new IssueRecords)->delete($id);
  }

  protected function close($id, $method) {
    $issue = self::getIssue($id);
    $issueBranches = (new IssueBranchFolders)->getLocal();
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
      if ($strict) throw new EmptyException("There is no opened issue with ID '$id'");
      else return false;
    }
    return $r[$id];
  }

  static function projectBranch($project, array $issue) {
    return $issue['masterBranch'];
    if ($project == $issue['project']) {
      return $issue['masterBranch'];
    } else {
      if (empty($issue['dependingProjectsMasterBranch'])) throw new EmptyException('dependingProjectsMasterBranch');
      return $issue['dependingProjectsMasterBranch'];
    }
  }



  /**
   * Показывает статус задачи
   */
  function status($id) {
    $issueBranches = (new IssueBranchFolders)->getLocal(IssueBranchFolders::returnFolder);
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
   * Аплоудит ветку на ремоут
   */
  function update($id) {
    $issueBranches = (new IssueBranchFolders)->getLocal();
    if (!isset($issueBranches[$id])) {
      output("No issue branches by ID $id");
      return;
    }
    $remote = self::$remote;
    foreach ($issueBranches[$id] as $f) {
      chdir($f);
      print `git add .`;
      print `git commit -am "..."`;
      sys("git push $remote i-$id", true);
    }
  }

  /**
   * Синхронизирует файл с данными о задачах в разработке с локальными ветками задач
   */
  function cleanupInDev() {
    foreach ((new IssueBranchFolders)->getLocal() as $id => $projects) {
      foreach ($projects as $project) {
        FileVar::updateSubVar(self::$inDevProjectsFile, $id, [
          'project'                       => $project,
          'masterBranch'                  => 'master',
          'dependingProjectsMasterBranch' => null
        ]);
      }
    }
  }

  static $remote;

}

Issue::$inDevProjectsFile = __DIR__.'/.inDevProjects.php';
Issue::$remote = file_exists(__DIR__.'/.remote') ? trim(file_get_contents(__DIR__.'/.remote')) : 'origin';
FileVar::touch(Issue::$inDevProjectsFile);