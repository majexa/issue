<?php

class IssueGitFolder extends GitFolder {

  function __construct($folder) {
    parent::__construct($folder);
    output2("Processing $this->folder");
  }

  function create($id, $masterBranch) {
    $this->shellexec("git checkout $masterBranch", true);
    $this->shellexec("git checkout -b i-$id", true);
  }

  function createNotClean($id, $masterBranch) {
    $this->shellexec("git add .", true);
    $this->create($id, $masterBranch);
    $this->shellexec("git commit -am \"init commit on i-$id branch\"", true);
    $this->shellexec("git checkout $masterBranch", true);
    $this->shellexec("git reset HEAD", true);
    $this->shellexec("git checkout i-$id", true);
  }

  function delete($id, $masterBranch) {
    $this->shellexec("git checkout $masterBranch", true);
    $this->shellexec("git branch -D i-$id", true);
    $this->shellexec("git push origin --delete i-$id", true);
  }

  function complete($id, $masterBranch) {
    $this->shellexec("git checkout $masterBranch", true);
    $this->shellexec("git merge i-$id", true);
    $this->shellexec("git branch -d i-$id", true);
    $this->shellexec("git push origin --delete i-$id", true);
  }

}