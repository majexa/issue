<?php

class Issue extends GitBase {

  function projects() {
    foreach ($this->findGitFolders() as $f) print '* '.basename($f)."\n";
  }

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

  function create($id, $project, $dependingProjects = '', $masterBranch = 'master', $dependingProjectsMasterBranch = 'master') {
    $dependingProjects = Misc::quoted2arr($dependingProjects);
    $projects = array_merge([$project], $dependingProjects);
    $notCleanProjects = $this->notCleanProjects($projects);
    $issueBranches = $this->getIssueBranches();
    if (isset($issueBranches[$id])) throw new Exception("Issue $id already started in projects: ".implode(', ', $issueBranches[$id]));
    if ($notCleanProjects and !Cli::confirm("Would U like to checkout dirty project changes to new 'i-$id' branch?\nDirty projects: ".implode(', ', $notCleanProjects))) return;
    foreach ($projects as $p) {
      $issueFolder = new IssueGitFolder($this->getGitProjectFolder($p));
      $masterBranch = $p == $project ? 'master' : $dependingProjectsMasterBranch;
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
      'dependingProjectsMasterBranch' => $dependingProjectsMasterBranch
    ]);
  }

  function add($id, $dependingProject, $masterBranch = 'master') {
    $isClean = !(new GitFolder($this->getGitProjectFolder($dependingProject)))->isClean();
    $issueFolder = new IssueGitFolder($this->getGitProjectFolder($dependingProject));
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

  //protected function test($id) {
  //}

  protected function initProjectGitFolders() {
    if (isset($this->projectGitFolders)) return;
    $this->projectGitFolders = [];
    foreach ($this->findGitFolders() as $f) $this->projectGitFolders[basename($f)] = $f;
  }

  function complete($id) {
    $this->close($id, 'complete');
  }

  function delete($id) {
    $this->close($id, 'delete');
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
    return $project == $issue['project'] ? $issue['masterBranch'] : $issue['dependingProjectsMasterBranch'];
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

  function opened() {
    //print "server '".gethostname()."':\n";
    $r = $this->getIssueBranches();
    if (!$r) {
      print "No opened issues\n";
      return;
    }
    foreach ($r as $issueId => $projects) {
      if (!($issue = $this->getIssue($issueId, false))) {
        output("No issue data for ID '$issueId'. Need to cleanup");
        return;
      }
      // $this->getGitProjectFolder($issue['project']);
      print "$issueId: ".implode(', ', $projects)."\n";
    }
  }

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
      print `git push $remote i-$id`;
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
Issue::$remote = trim(file_get_contents(__DIR__.'/.remote'));
FileVar::touch(Issue::$inDevProjectsFile);