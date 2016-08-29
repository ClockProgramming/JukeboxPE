<?php

namespace JukeboxPE\Updater;

use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use pocketmine\utils\Utils;
use pocketmine\utils\TextFormat as C;

use JukeboxPE\Main;

class UpdateTask extends AsyncTask {

  private $plugin;

  private $current_version;

  private $new_version;

  private $has_update;

  public function __construct(int $version){
    $this->current_version = $version;
    $this->has_update = null;
  }

  public function onRun(){
    $nversion = Utils::getURL("https://raw.githubusercontent.com/GlitchPlayer/JukeboxPE/master/resources/version");
    if($nversion > $this->version){
      $this->has_update = true;
    }

    else if($nversion == $this->version){
      $this->has_update = false;
    }

    else if($nversion < $this->version){
      $this->has_update = null;
    }
  }

  public function onCompletion(Server $server){
    if($this->has_update == true){
      $server->getLogger()->info(C::YELLOW . "A JukeboxPE Update has been found!");
    }

    else if($this->plugin->has_update == false){
      $server->getLogger()->info(C::AQUA . "No updates found! Your using the latest version of JukeboxPE!");

    }else{

      $server->getLogger()->warning("Invalid JukeboxPE Version!");
    }
  }
}

