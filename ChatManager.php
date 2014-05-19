<?php

/*
__PocketMine Plugin__
name=ChatManager
version=1.0
description=Very simple chat manager.
author=Lambo
class=cmanager
apiversion=12
*/

class cmanager implements Plugin{
        private $api;
        private $msgcount = 0;
        private $mutedPlayers=array();
        private $noMsgPlayers=array();

        public function __construct(ServerAPI $api, $server = false){
            $this->api = $api;
        }

        public function __destruct(){}

        public function init(){
        	$this->api->addHandler("player.chat", array($this,"onChat"));
            $this->api->console->register("mute", "<player> Mute/unmute a player", array($this, "cmd"));
            $this->api->console->register("muted", "<shows a list of all muted players>", array($this, "cmd"));
            $this->api->console->register("unmuteall", "<unmutes all muted players>", array($this, "cmd"));
        	$this->config = new Config($this->api->plugin->configPath($this)."config.yml", CONFIG_YAML, array(
                    "min-length for messages" => 5,
                    "max-length for messages" => 36,
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
                                "chat-format"=>"[@tag]@player: @message"
                	    	),
                            "another world if multichat is enabled"=> array(
                                "tag"=>"Mod",
                                "chat-format"=>"[@tag]@player: @message"
                            )
                	    ),
        				"donater"=> array(
        					"world"=>array(
        					    "tag"=>"Donater",
                                "chat-format"=>"[@tag]@player: @message"
        					),
                            "another world if multichat is enabled"=> array(
                                "tag"=>"Donater",
                                "chat-format"=>"[@tag]@player: @message"
                            )
        				),
                        "donater+"=> array(
                            "world"=>array(
                                "tag"=>"Donater+",
                                "chat-format"=>"[@tag]@player: @message"
                            ),
                            "another world if multichat is enabled"=> array(
                                "tag"=>"Donater+",
                                "chat-format"=>"[@tag]@player: @message"
                            )
                        )
        			),
        			"in-ranks"=>array(
        				"ranks" => array(
        		 	        "moderator"=>array(),
        		 	        "admin"=>array(),
        		 	        "owner"=>array(),
                            "donater"=>array(),
                            "donater+"=>array()
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

        public function onChat(&$data){
            $d=false;
            $player = $data["player"];
            $level = $player->entity->level;
            if($this->config["multichat (separate chat for each world)"]){
                if(!in_array($player->username,$this->mutedPlayers)){
                    if(strlen($data["message"]) <= $this->config["max-length for messages"]){
                        if(strlen($data["message"]) > $this->config["min-length for messages"]){
                            if($this->strposa($data["message"],$this->blockedWords->get("blocked-words"))){
                                $player->sendChat("Your message has been blocked.");
                            }else foreach($this->api->player->getAll($level) as $p) $p->sendChat($this->formatText($level,$data["player"]->username,$data["message"]));$d=true;
                        }else $player->sendChat("Your message is too short!");
                    }else $player->sendChat("Your message is too long!");
                }else $player->sendChat("You are muted!");
            }else{
                if(!in_array($player->username,$this->mutedPlayers)){
                    if($data["message"] <= $this->config["max-length for messages"]){
                        if($data["message"] > $this->config["min-length for messages"]){
                            if($this->strposa($data["message"],$this->blockedWords->get("blocked-words"))){
                                $player->sendChat("Your message has been blocked.");
                            }else foreach($this->api->player->getAll() as $p) $p->sendChat($this->formatText($level,$data["player"]->username,$data["message"]));$d=true;
                        }else $player->sendChat("Your message is too short!");
                    }else $player->sendChat("Your message is too long!");
                }else $player->sendChat("You are muted!");
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
            if(in_array($user,$this->config["in-ranks"]["ranks"]["donater"])) $rank="donater";
            if(in_array($user,$this->config["in-ranks"]["ranks"]["donater+"])) $rank="donater+";
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
                if(in_array($p[0],$this->mutedPlayers)){
                    array_splice($this->mutedPlayers,array_search($p[0],$this->mutedPlayers),1);
                    $issuer->sendChat("[ChatManager] ".$p[0]." has been un-muted.");
                    console("[ChatManager] ".$issuer->username." has un-muted ".$p[0]);
                }else{
                    array_push($this->mutedPlayers,$p[0]);
                    $issuer->sendChat("[ChatManager] ". $p[0] ." has been muted.");
                    console("[ChatManager] ".$issuer->username." has muted ".$p[0]);
                }
                case "muted":
                for($i = 0; $i < count($this->mutedPlayers); $i++){
                    $players .= $mutedPlayers[$i].", ";
                }
                $issuer->sendChat("[ChatManager] Muted players: ".$players);
                case "unmuteall":
                $issuer->sendChat("[ChatManager] ". count($this->mutedPlayers)." players have been un-muted.");
                $this->mutedPlayers = array();
                $this->api->chat->broadcast("[ChatManager] All players have been un-muted!");
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
