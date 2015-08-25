<?php
/**
 ** OVERVIEW:Trolling
 **
 ** COMMANDS
 **
 ** * freeze|thaw : freeze/unfreeze a player so they cannot move.
 **   usage: **freeze|thaw** [ _player_ | **--hard|--soft** ]
 **
 **   Stops players from moving.  If no player specified it will show
 **   the list of frozen players.
 **
 **   If `--hard` or `--soft` is specified instead of a player name, it
 **   will change the freeze mode.
 **
 ** CONFIG:freeze-thaw
 **/

namespace aliuly\grabbag;

use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;

use aliuly\grabbag\common\BasicCli;
use aliuly\grabbag\common\mc;
use aliuly\grabbag\common\MPMU;

class CmdFreezeMgr extends BasicCli implements Listener,CommandExecutor {
	protected $frosties;
	protected $hard;

	static public function defaults() {
		return [
			"# hard-freeze" => "how hard to freeze players.", // If `true` no movement is allowed.  If `false`, turning is allowed but not walking/running/flying, etc.
			"hard-freeze"=>false,
		];
	}

	public function __construct($owner,$cfg) {
		parent::__construct($owner);
		$this->hard = $cfg["hard-freeze"];
		$this->enableCmd("freeze",
							  ["description" => mc::_("freeze player"),
								"usage" => mc::_("/freeze [--hard|--soft] [player]"),
								"permission" => "gb.cmd.freeze"]);
		$this->enableCmd("thaw",
							  ["description" => mc::_("thaw player"),
								"usage" => mc::_("/thaw [player]"),
								"aliases" => ["unfreeze"],
								"permission" => "gb.cmd.freeze"]);
		$this->frosties = [];
		$this->owner->getServer()->getPluginManager()->registerEvents($this, $this->owner);
	}
	public function onCommand(CommandSender $sender,Command $cmd,$label, array $args) {
		if (count($args) == 0) {
			$sender->sendMessage(mc::_("Frozen: %1%",count($this->frosties)));
			if (count($this->frosties))
				$sender->sendMessage(implode(", ",$this->frosties));
			return true;
		}
		switch ($cmd->getName()) {
			case "freeze":
				if ($args[0] == "--hard") {
					$this->hard = true;
					$sender->sendMessage(mc::_("Now doing hard freeze"));
					$this->owner->cfgSave("freeze-thaw",["hard-freeze"=>$this->hard]);
					return true;
				} elseif ($args[0] == "--soft") {
					$this->hard = false;
					$sender->sendMessage(mc::_("Now doing soft freeze"));
					$this->owner->cfgSave("freeze-thaw",["hard-freeze"=>$this->hard]);
					return true;
				}

				foreach ($args as $n) {
					$player = $this->owner->getServer()->getPlayer($n);
					if ($player) {
						$this->frosties[strtolower($player->getName())] = $player->getName();
						$player->sendMessage(mc::_("You have been frozen by %1%",
															$sender->getName()));
						$sender->sendMessage(mc::_("%1% is frozen.",$n));
					} else {
						$sender->sendMessage(mc::_("%1% not found.",$n));
					}
				}
				return true;
			case "thaw":
				foreach ($args as $n) {
					if (isset($this->frosties[strtolower($n)])) {
						unset($this->frosties[strtolower($n)]);
						$player = $this->owner->getServer()->getPlayer($n);
						if ($player) {
							$player->sendMessage(mc::_("You have been thawed by %1%",
																$sender->getName()));
						}
						$sender->sendMessage(mc::_("%1% is thawed",$n));
					} else {
						$sender->sendMessage(mc::_("%1% not found or not thawed",$n));
					}
				}
				return true;
		}
		return false;
	}
	public function onMove(PlayerMoveEvent $ev) {
		//echo __METHOD__.",".__LINE__."\n";//##DEBUG
		if ($ev->isCancelled()) return;
		$p = $ev->getPlayer();
		if (isset($this->frosties[strtolower($p->getName())])) {
			if ($this->hard) {
				$ev->setCancelled();
				if (MPMU::apiVersion("1.12.0"))
					$p->sendTip(mc::_("You are frozen"));
			} else {
				// Lock position but still allow to turn around
				$to = clone $ev->getFrom();
				$to->yaw = $ev->getTo()->yaw;
				$to->pitch = $ev->getTo()->pitch;
				$ev->setTo($to);
				if (MPMU::apiVersion("1.12.0"))
					$p->sendTip(mc::_("You are frozen in place"));
			}
		}
	}
}
