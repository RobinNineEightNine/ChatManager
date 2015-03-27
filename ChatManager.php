<?php

/*
__PocketMine Plugin__
name=ChatManager
version=1.2
description=Very simple chat manager.
author=Lambo
class=cmanager
apiversion=12
*/

class cmanager implements Plugin{
        private $api;
        private $msgcount = 0;
        private $mutedPlayers=array();
        private $mutedPlayersData=array();
        private $noMsgPlayers=array();
        private $players=array();

        public function __construct(ServerAPI $api, $server = false){
            $this->api = $api;
        }

        public function __destruct(){}

        public function init(){
            $this->api->addHandler("player.quit",array($this,"onLeave"));
        	$this->api->addHandler("player.chat", array($this,"onChat"));
            $this->api->console->register("mute", "<player> Mute/unmute a player", array($this, "cmd"));
            $this->api->console->register("muted", "<shows a list of all muted players>", array($this, "cmd"));
            $this->api->console->register("unmuteall", "<unmutes all muted players>", array($this, "cmd"));
        	$this->config = new Config($this->api->plugin->configPath($this)."config.yml", CONFIG_YAML, array(
                    "min-length for messages" => 5,
                    "log chat messages"=>false,
                    "max-length for messages" => 36,
                    "prevent spam" =>true,
                    "delay between each message (seconds)" =>8,
                    "console chat"=>true,
                    "multichat (separate chat for each world)"=> true,
        			"ranks"=> array(
        				"default"=> array(
        					"world"=> array(
        					    "tag"=>"Player",
                                "chat-format"=>"[@tag]@player: @message"
        					),
                            "another world if multichat is enabled"=> array(
                                "tag"=>"Player",
                                "chat-format"=>"[@tag]@player: @message"
                            )
        				),
        				"moderator"=> array(
        					"world"=>array(
        					    "tag"=>"Mod",
                                "chat-format"=>"[@tag]@player: @message"
        					),
                            "another world.."=> array(
                                "tag"=>"Mod",
                                "chat-format"=>"[@tag]@player: @message"
                            )
        				),
        				"admin"=> array(
        					"world"=>array(
        					    "tag"=>"Admin",
                                "chat-format"=>"[@tag]@player: @message"
        					),
                            "another world if multichat is enabled"=> array(
                                "tag"=>"Admin",
                                "chat-format"=>"[@tag]@player: @message"
                            )
        				),
                	    "owner"=>array(
                	    	"world"=>array(
                	    	    "tag"=>"Owner",
                                "chat-format"=>"[Robin989@tag]@player: @message"
                	    	),
                            "another world if multichat is enabled"=> array(
                                "tag"=>"Mod",
                                "chat-format"=>"[@tag]@player: @message"
                            )
                	    ),
        				"donator"=> array(
        					"world"=>array(
        					    "tag"=>"Donator",
                                "chat-format"=>"[@tag]@player: @message"
        					),
                            "another world if multichat is enabled"=> array(
                                "tag"=>"Donator",
                                "chat-format"=>"[@tag]@player: @message"
                            )
        				),
                        "donator+"=> array(
                            "world"=>array(
                                "tag"=>"Donator+",
                                "chat-format"=>"[@tag]@player: @message"
                            ),
                            "another world if multichat is enabled"=> array(
                                "tag"=>"Donator+",
                                "chat-format"=>"[@tag]@player: @message"
                            )
                        )
        			),
        			"in-ranks"=>array(
        				"ranks" => array(
        		 	        "moderator"=>array(),
        		 	        "admin"=>array(),
        		 	        "owner"=>array(),
                            "donator"=>array(),
                            "donator+"=>array()
        		  	    )
        			)
        	));
            $this->automessage = new Config($this->api->plugin->configPath($this)."auto-broadcast.yml", CONFIG_YAML, array(
                "enabled" => false,
                "console messages" => false,
                "delay (seconds)" => 120,
                "messages" => array(
                    "0" => "[Broadcaster] Hello world!",
                    "1" => "[Broadcaster] Have fun!",
                    "2" => "[Broadcaster] You can keep adding messages like this."
                )
            ));
            $this->blockedWords = new Config($this->api->plugin->configPath($this)."blocked-words.yml", CONFIG_YAML, array("blocked-words"=>array("fuck","shit","cunt")));
            $this->config = $this->api->plugin->readYAML($this->api->plugin->configPath($this) . "config.yml");
            $this->api->schedule($this->automessage->get("delay (seconds)") * 20, array($this, "autoMessage"), array(), true);
        }

        public function onLeave($player){
            if(isset($this->players[$player->username])) $this->players[$player->username]=array("msg"=>"","time"=>0);
        }

        public function onChat($data){
            $d=false;
            $player = $data["player"];
            $level = $player->entity->level;
            if(!isset($this->players[$player->username])) $this->players[$player->username]=array("msg"=>"","time"=>0);
            if($this->config["multichat (separate chat for each world)"]){
                if(!in_array($player->username,$this->mutedPlayers)){
                    if(strlen($data["message"]) <= $this->config["max-length for messages"]){
                        if(strlen($data["message"]) > $this->config["min-length for messages"]){
                            $a = array("moderator","vip","admin","donator","donator+","owner");
                            if($this->strposa($data["message"],$this->blockedWords->get("blocked-words"))){
                                $player->sendChat("Your message has been blocked.");
                            }else
                            if((time() - $this->players[$player->username]["time"]) > $this->config["delay between each message (seconds)"] and $this->getRank($player->username)=="default"){
                                if($this->players[$player->username]["msg"] != $data["message"]){
                                    foreach($this->api->player->getAll($level) as $p) $p->sendChat($this->formatText($level,$data["player"]->username,$data["message"]));$this->players[$player->username]=array("time"=>time(),"msg"=>$data["message"]);$d=true;
                                }else $player->sendChat("You cannot send the same message!");
                            }else
                            if($this->strposa($this->getRank($player->username),$a)){
                                foreach($this->api->player->getAll() as $p) $p->sendChat($this->formatText($level,$data["player"]->username,$data["message"]));$this->players[$player->username]=array("time"=>time(),"msg"=>$data["message"]);$d=true;
                            }else $player->sendChat("Please wait ".($this->config["delay between each message (seconds)"] - (time() - $this->players[$player->username]["time"]))." seconds.");
                        }else $player->sendChat("Your message is too short!");
                    }else $player->sendChat("Your message is too long!");
                }else
                if($this->mutedPlayersData[$player->username]["time"]!=null){
                    $a=time()-$this->mutedPlayersData[$player->username]["time"];
                    if($a>$this->mutedPlayersData[$player->username]["mutetime"]){
                        array_splice($this->mutedPlayers,array_search($player->username,$this->mutedPlayers),1);
                        array_splice($this->mutedPlayersData,array_search($player->username,$this->mutedPlayersData),1);
                        $player->sendChat("You have been un-muted.");
                    }else{
                        $player->sendChat("You are still muted for ".($this->mutedPlayersData[$player->username]["min"] - $a)." seconds.");
                    }
                }
            }else{
                if(!in_array($player->username,$this->mutedPlayers)){
                    if(strlen($data["message"]) <= $this->config["max-length for messages"]){
                        if(strlen($data["message"]) > $this->config["min-length for messages"]){
                            $a = array("moderator","vip","admin","donator","donator+","owner");
                            if($this->strposa($data["message"],$this->blockedWords->get("blocked-words"))){
                                $player->sendChat("Your message has been blocked.");
                            }else
                            if((time() - $this->players[$player->username]["time"]) > $this->config["delay between each message (seconds)"] and $this->getRank($player->username)=="default"){
                                if($this->players[$player->username]["msg"] != $data["message"]){
                                    foreach($this->api->player->getAll() as $p) $p->sendChat($this->formatText($level,$data["player"]->username,$data["message"]));$this->players[$player->username]=array("time"=>time(),"msg"=>$data["message"]);$d=true;
                                }else $player->sendChat("You cannot send the same message!");
                            }else
                            if($this->strposa($this->getRank($player->username),$a)){
                                foreach($this->api->player->getAll() as $p) $p->sendChat($this->formatText($level,$data["player"]->username,$data["message"]));$this->players[$player->username]=array("time"=>time(),"msg"=>$data["message"]);$d=true;
                            }else $player->sendChat("Please wait ".($this->config["delay between each message (seconds)"] - (time() - $this->players[$player->username]["time"]))." seconds.");
                        }else $player->sendChat("Your message is too short!");
                    }else $player->sendChat("Your message is too long!");
                }else
                if($this->mutedPlayersData[$player->username]["time"]!=null){
                    $a=time()-$this->mutedPlayersData[$player->username]["time"];
                    if($a>$this->mutedPlayersData[$player->username]["mutetime"]){
                        array_splice($this->mutedPlayers,array_search($player->username,$this->mutedPlayers),1);
                        array_splice($this->mutedPlayersData,array_search($player->username,$this->mutedPlayersData),1);
                        $player->sendChat("You have been un-muted.");
                    }else{
                        $player->sendChat("You are still muted for ".($this->mutedPlayersData[$player->username]["min"] - $a)." seconds.");
                    }
                }
            }
            if(!in_array($player,$this->mutedPlayers)){
                if($this->config["console chat"]){
                    if($d) console("[CHAT][".$level->getName()."]".$this->formatText($level,$data["player"]->username,$data["message"]));
                }
            }else console("[CHAT] ".$player->username." is trying to chat, but is muted.");
            return false;
            break;
        }

        public function formatText(Level $level,$username,$message){
            $msg="";
            if(!isset($this->config["ranks"][$this->getRank($username)][$level->getName()])){
                $this->config["ranks"][$this->getRank($username)][$level->getName()]["tag"] = $this->config["ranks"][$this->getRank($username)]["world"]["tag"];
                $this->config["ranks"][$this->getRank($username)][$level->getName()]["chat-format"] = $this->config["ranks"][$this->getRank($username)]["world"]["chat-format"];
                $this->api->plugin->writeYAML($this->api->plugin->configPath($this) . "config.yml", $this->config);
            }
            if($this->config["multichat (separate chat for each world)"]){
                $msg=str_replace(array("@level","@tag","@player","@message"),array($level->getName(),$this->config["ranks"][$this->getRank($username)][$level->getName()]["tag"],$username,$message),$this->config["ranks"][$this->getRank($username)][$level->getName()]["chat-format"]);
            }else $msg=str_replace(array("@level","@tag","@player","@message"),array($level->getName(),$this->config["ranks"][$this->getRank($username)][$level->getName()]["tag"],$username,$message),$this->config["ranks"][$this->getRank($username)][$level->getName()]["chat-format"]);
            return $msg;
        }

        public function getRank($user){
            $rank="default";
        	if(in_array($user,$this->config["in-ranks"]["ranks"]["moderator"])) $rank="moderator";
            if(in_array($user,$this->config["in-ranks"]["ranks"]["admin"])) $rank="admin";
            if(in_array($user,$this->config["in-ranks"]["ranks"]["owner"])) $rank="owner";
            if(in_array($user,$this->config["in-ranks"]["ranks"]["donator"])) $rank="donator";
            if(in_array($user,$this->config["in-ranks"]["ranks"]["donator+"])) $rank="donator+";
            return $rank;
        }

        public function autoMessage(){
            $m=null;
            if($this->automessage->get("enabled")){
                if($this->msgcount == count($this->automessage->get("messages"))) $this->msgcount = 0;
                foreach($this->api->player->getAll() as $p) $p->sendChat($this->automessage->get("messages")[$this->msgcount]);
                $m=$this->automessage->get("messages")[$this->msgcount];
                if($this->msgcount != count($this->automessage->get("messages"))) $this->msgcount++;
            }
            if($this->automessage->get("console messages")) console("[CHAT] ".$m);
        }

        public function cmd($cmd, $p, $issuer){
            switch($cmd){
                case "mute":
                if(isset($p[0])){
                    if(in_array($p[0],$this->mutedPlayers)){
                        array_splice($this->mutedPlayers,array_search($p[0],$this->mutedPlayers),1);
                        array_splice($this->mutedPlayersData,array_search($p[0],$this->mutedPlayersData),1);
                        return "[ChatManager] ".$p[0]." has been un-muted.";
                        if($issuer instanceof Player) console("[ChatManager] ".$issuer->username." has un-muted ".$p[0]);
                        if($this->api->player->get($p[0]) instanceof Player) $p[0]->sendChat("[ChatManager] You have been un-muted.");
                    }else{
                        if(!isset($p[1])) $p[1] = 30;
                        array_push($this->mutedPlayers,$p[0]);
                        $this->mutedPlayersData[$p[0]]=array("time"=>time(),"mutetime"=>$p[1]*60,"min"=>$p[1]*60);
                        return "[ChatManager] ". $p[0] ." has been muted for ".$p[1]." minutes.";
                        if($issuer instanceof Player) console("[ChatManager] ".$issuer->username." has muted ".$p[0]);
                        if($this->api->player->get($p[0]) instanceof Player) $p[0]->sendChat("[ChatManager] You have been muted for ".$p[1]." minutes.");
                    }
                }else return "[ChatManager] Usage: /mute <username> <minutes>";
                case "muted":
                return "[ChatManager] Muted players: ".implode(", ",$this->mutedPlayers);
                case "unmuteall":
                return "[ChatManager] ". count($this->mutedPlayers)." players have been un-muted.";
                $this->mutedPlayers = array();
                $this->api->chat->broadcast("[ChatManager] All players have been un-muted!");
                break;
            }
        }

        public function strposa($haystack, $needle, $offset=0) {
            if(!is_array($needle)) $needle = array($needle);
            foreach($needle as $query) {
                if(strpos($haystack, $query, $offset) !== false) return true;
            }
            return false;
        }
}
