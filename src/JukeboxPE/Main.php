<?php
namespace JukeboxPE;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;

use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;

use pocketmine\event\Listener;

use pocketmine\level;
use pocketmine\Server;

use pocketmine\scheduler\PluginTask;

use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

use pocketmine\network\protocol\BlockEventPacket;
use pocketmine\network\protocol\LevelSoundEventPacket;

use pocketmine\Player;

use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\math\Math;

use pocketmine\level\format\Chunk;
use pocketmine\level\format\FullChunk;

use pocketmine\utils\BinaryStream;
use pocketmine\utils\Binary;

use JukeboxPE\JukeboxAPI;
use JukeboxPE\Updater\UpdateTask;

class Main extends PluginBase implements Listener {
    public $song;
    public $SongPlayer;
    public $name;
    
    const PACKAGE_VERSION = "5.7";

    public function onEnable() {
        $this->saveDefaultConfig();
        $this->reloadConfig();
        $this->getLogger()->info("JukeboxPE is loading!");
        $this->getServer()->getScheduler()->scheduleAsyncTask($task = new UpdateTask($this->getVersion()));
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        if(!is_dir($this->getPluginDir())) {
            @mkdir($this->getServer()->getDataPath()."plugins/songs");
        }
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
        if(!$this->CheckMusic()) {
            $this->getLogger()->info("§bPlease put in NBS type files!!!");
        }else{
            $this->StartNewTask();
        }
        $this->getLogger()->notice(TextFormat::GREEN."Enabled!");
    }
    
    private function checkUpdate() {
		try{
			$info = json_decode(Utils::getURL($this->getConfig()->get("update-host")."?version=".$this->getDescription()->getVersion()."&package_version=".self::PACKAGE_VERSION), true);
			if(!isset($info["status"]) or $info["status"] !== true){
				$this->getLogger()->notice("Something went wrong on update server.");
				return false;
			}
			if($info["update-available"] === true) {
				$this->getLogger()->notice("Server says new version (".$info["new-version"].") of EconomyS is out. Check it out at ".$info["download-address"]);
			}
			$this->getLogger()->notice($info["notice"]);
			return true;
		}catch(\Throwable $e) {
			$this->getLogger()->logException($e);
			return false;
		}
	}

    public function setVersion(int $version){
      $cfg = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
      $cfg->set("Version", $version);
      $cfg->save();
      $cfg->reload();
    }

    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args) {
        switch($cmd->getName()) {
            case "music":
                if(isset($args[0])) {
                    switch($args[0]) {
                        case "next":
                            $this->StartNewTask();
                            $sender->sendMessage(TextFormat::GREEN."Switched to next song");
                            return true;
                            break;
                        case "stop":
                        case "pause":
                            if($sender->hasPermission("JukeboxPE.cmd.music")) {
                                $this->getServer()->getScheduler()->cancelTasks($this);
                                $sender->sendMessage(TextFormat::GREEN."Song Stopped");
                            }else{
                                $sender->sendMessage(TextFormat::RED."No Permission");
                            }
                            return true;
                            break;
                        case "start":
                        case "play":
                            if($sender->hasPermission("JukeboxPE.cmd.music")) {
                                $this->StartNewTask();
                                $sender->sendMessage(TextFormat::GREEN."Song Started");
                            }else{
                                $sender->sendMessage(TextFormat::RED."No Permission");
                            }
                            return true;
                            break;
                    }
                }else{
                    return false;
                }
                break;
        }
        return false;
    }

    public function CheckMusic() {
        if($this->getDirCount($this->getPluginDir()) > 0 and $this->RandomFile($this->getPluginDir(),"nbs")) {
            return true;
        }
        return false;
    }

    public function getDirCount($PATH) {
        $num = sizeof(scandir($PATH));
        $num = ($num>2)?$num-2:0;
        return $num;
    }

    public function getPluginDir() {
        return $this->getServer()->getDataPath()."plugins/songs/";
    }

    public function getRandomMusic() {
        $dir = $this->RandomFile($this->getPluginDir(),"nbs");
        if($dir) {
            $api = new JukeBoxAPI($this,$dir);
            return $api;
        }
        return false;
    }

    Public function RandomFile($folder='', $extensions='.*') {
        $folder = trim($folder);
        $folder = ($folder == '') ? './' : $folder;
        if (!is_dir($folder)) {
            return false;
        }
        $files = array();
        if ($dir = @opendir($folder)) {
            while($file = readdir($dir)) {
                if (!preg_match('/^\.+$/', $file) and
                    preg_match('/\.('.$extensions.')$/', $file)) {
                    $files[] = $file;
                }
            }
            closedir($dir);
        }else{
            return false;
        }
        if (count($files) == 0) {
            return false;
        }
        mt_srand((double)microtime()*1000000);
        $rand = mt_rand(0, count($files)-1);
        if (!isset($files[$rand])) {
            return false;
        }
        if(function_exists("iconv")) {
            $rname = iconv('gbk','UTF-8',$files[$rand]);
        }else{
            $rname = $files[$rand];
        }
        $this->name = str_replace('.nbs', '', $rname);
        return $folder . $files[$rand];
    }

    public function getNearbyNoteBlock($x,$y,$z,$world) {
        $nearby = [];
        $minX = $x - 5;
        $maxX = $x + 5;
        $minY = $y - 5;
        $maxY = $y + 5;
        $minZ = $z - 2;
        $maxZ = $z + 2;

        for($x = $minX; $x <= $maxX; ++$x) {
            for($y = $minY; $y <= $maxY; ++$y) {
                for($z = $minZ; $z <= $maxZ; ++$z) {
                    $v3 = new Vector3($x, $y, $z);
                    $block = $world->getBlock($v3);
                    if($block->getID() == 25) {
                        $nearby[] = $block;
                    }
                }
            }
        }
        return $nearby;
    }

    public function getFullBlock($x, $y, $z, $level) {
        return $level->getChunk($x >> 4, $z >> 4, false)->getFullBlock($x & 0x0f, $y & 0x7f, $z & 0x0f);
    }

    public function Play($sound,$type = 0,$blo = 0) {
        if(is_numeric($sound) and $sound > 0) {
            foreach($this->getServer()->getOnlinePlayers() as $p) {
                $noteblock = $this->getNearbyNoteBlock($p->x,$p->y,$p->z,$p->getLevel());
                $noteblock1 = $noteblock;
                if(!empty($noteblock)) {
                    if($this->song->name != "") {
                        $p->sendPopup("§b|->§6Now Playing: §a".$this->song->name."§b<-|");
                    }else{
                        $p->sendPopup("§b|->§6Now Playing: §a".$this->name."§b<-|");
                    }
                    $i = 0;
                    while ($i < $blo) {
                        if(current($noteblock)) {
                            next($noteblock);
                            $i ++;
                        }else{
                            $noteblock = $noteblock1;
                            $i ++;
                        }
                    }
                    $block = current($noteblock);
					if($block){
						$pk = new BlockEventPacket();
						$pk->x = $block->x;
						$pk->y = $block->y;
						$pk->z = $block->z;
						$pk->case1 = $type;
						$pk->case2 = $sound;
						$p->dataPacket($pk);
						$pk = new LevelSoundEventPacket();
						$pk->sound = 64;
						$pk->x = $block->x;
						$pk->y = $block->y;
						$pk->z = $block->z;
						$pk->volume = $type;
						$pk->pitch = $sound;
						$pk->unknownBool = true;
						$pk->unknownBool2 = true;
						$p->dataPacket($pk);
					}
                }
            }
        }
    }


    public function getVersion(){
      $cfg = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
      return (int) $cfg->get("Version");
    }

    public function hasUpdate(){
      return;
    }

    public function onDisable() {
        $this->getLogger()->notice(TextFormat::GREEN."Disabled!");
    }

    public function StartNewTask() {
        $this->song = $this->getRandomMusic();
        $this->getServer()->getScheduler()->cancelTasks($this);
        $this->SongPlayer = new SongPlayer($this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask($this->SongPlayer, 3000 / $this->song->speed );
    }

}
