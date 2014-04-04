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
  function changed() {
    $this->_changed();
  }

  protected function _changed($filter = []) {
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
      $git = new GitFolder($folder);
      if (!$git->isClean()) {
        $r[] = $folder;
      }
    }
    return $r;
  }

  /**
   * Синхронизирует изменения с ремоутом
   */
  function push($projectsFilter = []) {
    print "You trying to push this projects to all theirs remotes:\n";
    $this->_changed($projectsFilter);
    if (!Cli::confirm('Are you shure?')) return;
    foreach ($this->getChangedFolders($projectsFilter) as $folder) {
      (new GitFolder($folder))->push();
    }
  }

}
