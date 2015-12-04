<?php

class IssueBranchFolders extends GitBase {

  function getLocal($return = Issue::returnProject) {
    $r = [];
    foreach ($this->findGitFolders() as $f) {
      chdir($f);
      $branches = `git branch`;
      foreach (explode("\n", $branches) as $name) {
        $name = trim(Misc::removePrefix('* ', $name));
        if (Misc::hasPrefix('i-', $name)) {
          $r[Misc::removePrefix('i-', $name)][] = ($return == Issue::returnProject ? basename($f) : $f);
        }
      }
    }
    return $r;
  }

  function get() {
    $r = [];
    foreach ($this->findGitFolders() as $f) {
      chdir($f);
      $branches = `git branch -a`;
      foreach (explode("\n", $branches) as $name) {
        $name = trim(Misc::removePrefix('* ', $name));
        $name = Misc::removePrefix('remotes/origin/', $name);
        if (Misc::hasPrefix('i-', $name)) {
          $k = Misc::removePrefix('i-', $name);
          if (!isset($r[$k])) $r[$k] = [];
          elseif (in_array($f, $r[$k])) continue;
          $r[$k][] = $f;
        }
      }
    }
    return $r;
  }

}