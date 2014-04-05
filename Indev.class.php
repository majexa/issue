<?php

class Indev extends GitBase {

  /**
   * Отображает все гит-проекты
   */
  function projects() {
    foreach ($this->findGitFolders() as $f) print '* '.basename($f)."\n";
  }

  /**
   * Отображает активные ветки всех гит-проектов
   */
  function branches() {
    foreach ($this->findGitFolders() as $folder) {
      print '* '.str_pad(basename($folder), 20).(new GitFolder($folder))->wdBranch()."\n";
    }
  }

  /**
   * Показывает гит-проекты, нуждающиеся в пуше или пуле
   */
  function tocommit() {
    $this->notClean();
  }

  protected function notClean($filter = []) {
    foreach ($this->findGitFolders($filter) as $folder) {
      $git = new GitFolder($folder);
      if (!$git->isClean()) {
        print '* '.str_pad(basename($folder), 20).$git->wdBranch()."\n";
      }
    }
  }

  protected function getChangedFolders($filter = []) {
    $r = [];
    foreach ($this->findGitFolders($filter) as $folder) {
      if ((new GitFolder($folder))->hasChanges()) $r[] = $folder;
    }
    return $r;
  }

  function topush() {
    foreach ($this->findGitFolders() as $folder) {
      $git = new GitFolder($folder);
      if ($git->hasChanges()) {
        $remotes = implode(', ', $git->getRemotes($git->wdBranch()));
        if (!$remotes) $remotes = 'origin (new)';
        print '* '.str_pad(basename($folder), 20).str_pad($git->wdBranch(), 10).'> '.$remotes."\n";
      }
    }
  }

  /**
   * Синхронизирует изменения с ремоутом
   */
  function push($projectsFilter = []) {
    print "You trying to push this projects to all theirs remotes:\n";
    $this->topush($projectsFilter);
    if (!Cli::confirm('Are you shure?')) return;
    foreach ($this->getChangedFolders($projectsFilter) as $folder) { // !
      (new GitFolder($folder))->push();
    }
  }

}
