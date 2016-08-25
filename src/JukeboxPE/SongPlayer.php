<?php
namespace JukeboxPE;

use pocketmine\scheduler\PluginTask;

class SongPlayer extends PluginTask{
    public function __construct(Main $plugin){
        parent::__construct($plugin);
        $this->plugin = $plugin;
    }

    public function onRun($CT){
        if(isset($this->plugin->song->sounds[$this->plugin->song->tick])){
            $i = 0;
            foreach($this->plugin->song->sounds[$this->plugin->song->tick] as $data){
                $this->plugin->Play($data[0],$data[1],$i);
                $i++;
            }
        }
        $this->plugin->song->tick++;
        if($this->plugin->song->tick > $this->plugin->song->length){
            $this->plugin->StartNewTask();
        }
    }

}