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
  function commit($projectsFilter = []) {
    $this->abstractConfirmAction($projectsFilter, 'commit', 'getNotCleanFolders', 'You trying to commit these projects');
  }

  /**
   * Синхронизирует изменения с ремоутом
   */
  function push($projectsFilter = []) {
    $this->abstractConfirmAction($projectsFilter, 'push', 'getChangedFolders', 'You trying to push these projects to all theirs remotes');
  }

  protected function abstractConfirmAction($projectsFilter, $actionMethod, $getFoldersMethod, $confirmCaption) {
    $folders = $this->$getFoldersMethod($projectsFilter);
    if (!$folders) {
      print "No projects to $actionMethod\n";
      return;
    }
    print "$confirmCaption:\n";
    $projectsListAction = $actionMethod.'Info';
    $this->$projectsListAction($projectsFilter);
    if (!Cli::confirm('Are you shure?')) return;
    foreach ($folders as $folder) { // !
      (new GitFolder($folder))->$actionMethod();
    }
  }

  protected function pushInfo() {
    foreach ($this->findGitFolders() as $folder) {
      $git = new GitFolder($folder);
      if ($git->hasChanges()) {
        $remotes = implode(', ', $git->getRemotes($git->wdBranch()));
        if (!$remotes) $remotes = 'origin (new)';
        print '* '.str_pad(basename($folder), 20).str_pad($git->wdBranch(), 10).'> '.$remotes."\n";
      }
    }
  }

  protected function commitInfo($filter = []) {
    foreach ($this->findGitFolders($filter) as $folder) {
      $git = new GitFolder($folder);
      if (!$git->isClean()) {
        print '* '.str_pad(basename($folder), 20).$git->wdBranch()."\n";
      }
    }
  }

  protected function getNotCleanFolders($filter = []) {
    return array_filter($this->findGitFolders($filter), function($folder) {
      return !(new GitFolder($folder))->isClean();
    });
  }

  protected function getChangedFolders($filter = []) {
    return array_filter($this->findGitFolders($filter), function($folder) {
      return (new GitFolder($folder))->hasChanges();
    });
  }

}
