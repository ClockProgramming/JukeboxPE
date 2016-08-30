<?php

namespace JukeboxPE\Updater;

use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Utils;
use pocketmine\utils\TextFormat as C;

use JukeboxPE\Main;

class UpdateTask extends AsyncTask {

  private Plugin $plugin = null;

  private $current_version;

  private $new_version = null;

  private $has_update = false;

  public function __construct($version) {
    $this->current_version = $version;
    $this->has_update = null;
  }

  public function onRun() {
    $nversion = Utils::getURL("https://raw.githubusercontent.com/GlitchPlayer/JukeboxPE/gh-pages/current_version.txt");
    if($nversion > $this->version) {
      $this->has_update = true;
    }

    else if($nversion == $this->version) {
      $this->has_update = false;
    }

    else if($nversion < $this->version) {
      $this->has_update = null;
    }
  }

  public function onCompletion() {
    if($this->has_update == true) {
      $this->plugin->getLogger()->info(C::YELLOW . "A JukeboxPE Update has been found!");
    }

    else if($this->plugin->has_update == false) {
      $server->getLogger()->info(C::AQUA . "No updates found! Your using the latest version of JukeboxPE!");

    }else{

      $server->getLogger()->warning("Invalid JukeboxPE Version!");
    }
  }
}
