<?php

class IssueBranchFolders extends GitBase {

  const returnFolder = 1, returnProject = 2;

  /**
   * Возвращает каталоги с локальными ветками задач
   */
  function getLocal() {
    return $this->get(self::typeLocal);
  }

  /**
   * Возвращает каталоги с ветками задач на ремоуте
   */
  function getRemote() {
    return $this->get(self::typeRemote);
  }

  /**
   * Возвращает каталоги с ветками задач
   */
  function getAll() {
    return $this->get(self::typeAll);
  }

  /**
   * Возвращает каталоги с ветками задач, отсутствующих на ремоуте
   */
  function getRemoved() {
    $remoteIssueBranches = $this->getRemote();
    $localIssueBranches = $this->getLocal();
    $removedIssueBranches = [];
    foreach (array_diff(array_keys($localIssueBranches), array_keys($remoteIssueBranches)) as $removedIssueId) {
      $removedIssueBranches[$removedIssueId] = $localIssueBranches[$removedIssueId];
    }
    return $removedIssueBranches;
  }

  const typeLocal = 1, typeRemote = 2, typeAll = 3;

  protected function get($type = self::typeAll) {
    $r = [];
    if ($type == self::typeAll) {
      $suffix = ' -a';
    }
    elseif ($type == self::typeRemote) {
      $suffix = ' -r';
    }
    else {
      $suffix = '';
    }
    foreach ($this->findGitFolders() as $f) {
      chdir($f);
      $branches = `git branch$suffix`;
      foreach (explode("\n", $branches) as $name) {
        $name = trim(Misc::removePrefix('* ', $name));
        $name = trim(Misc::removePrefix('remotes/origin/', $name));
        if (Misc::hasPrefix('i-', $name)) {
          $r[Misc::removePrefix('i-', $name)][] = $f;
        }
      }
    }
    return $r;
  }
}