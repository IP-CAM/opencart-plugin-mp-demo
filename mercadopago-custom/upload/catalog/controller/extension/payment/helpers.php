<?php
  public function set_val($tag, $dest, $this){
    $dest[$tag] = $this->language->get($tag);
  }
?>
