<?php namespace ALttP;

use ALttP\Support\Dialog;
use ALttP\Support\ItemCollection;
use Log;

/**
 * Wrapper for ROM file
 */
class Rom {
	const BUILD = '2017-08-11';
	const HASH = '7f112e52921bf72c67296848f3f4ad97';
	const SIZE = 2097152;
	static private $digit_gfx = [
		0 => 0x30,
		1 => 0x31,
		2 => 0x02,
		3 => 0x03,
		4 => 0x12,
		5 => 0x13,
		6 => 0x22,
		7 => 0x23,
		8 => 0x32,
		9 => 0x33,
	];

	private $tmp_file;
	protected $rom;
	protected $write_log = [];

	/**
	 * Save a ROM build the DB for later retervial if someone is patching for an old seed.
	 *
	 * @param array $patch for the build
	 *
	 * @return Build
	 */
	public static function saveBuild(array $patch) : Build {
		$build = Build::firstOrCreate([
			'build' => static::BUILD,
			'hash' => static::HASH,
		]);
		$build->patch = json_encode($patch);
		$build->save();

		return $build;
	}

	/**
	 * Create a new wrapper
	 *
	 * @param string $source_location location of source ROM to edit
	 *
	 * @return void
	 */
	public function __construct(string $source_location = null) {
		if ($source_location !== null && !is_readable($source_location)) {
			throw new \Exception('Source ROM not readable');
		}
		$this->tmp_file = tempnam(sys_get_temp_dir(), __CLASS__);

		if ($source_location !== null) {
			copy($source_location, $this->tmp_file);
		}

		$this->rom = fopen($this->tmp_file, "r+");
	}

 	/**
	 * resize ROM to a given size
	 *
	 * @param int|null $size number of bytes the ROM should be
	 *
	 * @return $this
	 *
	 */
	public function resize(int $size = null) : self {
		ftruncate($this->rom, $size ?? static::SIZE);

		return $this;
	}

	/**
	 * Check to see if this ROM matches base randomizer ROM.
	 *
	 * @return bool
	 */
	public function checkMD5() : bool {
		return $this->getMD5() === static::HASH;
	}

	/**
	 * Get MD5 of current file.
	 *
	 * @return string
	 */
	public function getMD5() : string {
		return hash_file('md5', $this->tmp_file);
	}

	/**
	 * Update the ROM's checksum to be proper
	 *
	 * @return $this
	 */
	public function updateChecksum() : self {
		fseek($this->rom, 0x0);
		$sum = 0;
		for ($i = 0; $i < static::SIZE; $i += 1024) {
			$bytes = array_values(unpack('C*', fread($this->rom, 1024)));
			for ($j = 0; $j < 1024; ++$j) {
				if ($j + $i >= 0x7FB0 && $j + $i <= 0x7FE0) {
					// this skip is true for LoROM, HiROM skips: 0xFFB0 - 0xFFB0
					continue;
				}
				$sum += $bytes[$j];
			}
		}

		$checksum = $sum & 0xFFFF;
		$inverse = $checksum ^ 0xFFFF;

		$this->write(0x7FDC, pack('S*', $inverse, $checksum));

		return $this;
	}

	/**
	 * Set the Low Health Beep Speed
	 *
	 * @param string $setting name (0x00: off, 0x20: normal, 0x40: half, 0x80: quarter)
	 *
	 * @return $this
	 */
	public function setHeartBeepSpeed(string $setting) : self {
		switch ($setting) {
			case 'off':
				$byte = 0x00;
				break;
			case 'half':
				$byte = 0x40;
				break;
			case 'quarter':
				$byte = 0x80;
				break;
			case 'normal':
			default:
				$byte = 0x20;
		}

		$this->write(0x180033, pack('C', $byte));

		return $this;
	}

	/**
	 * Set the Rupoor value to take rupees
	 *
	 * @param int $value
	 *
	 * @return $this
	 */
	public function setRupoorValue(int $value = 10) : self {
		$this->write(0x180036, pack('v*', $value));

		return $this;
	}

	/**
	 * Set Cane of Byrna Cave spike floor damage
	 *
	 * @param int $dmg_value (0x08: 1 Heart, 0x02: 1/4 Heart)
	 *
	 * @return $this
	 */
	public function setByrnaCaveSpikeDamage(int $dmg_value = 0x08) : self {
		$this->write(0x180168, pack('C*', $dmg_value));

		return $this;
	}

	/**
	 * Set Cane of Byrna Cave and Misery Mire spike room Byrna usage
	 *
	 * @param int $normal normal magic usage
	 * @param int $half half magic usage
	 * @param int $quarter quarter magic usage
	 *
	 * @return $this
	 */
	public function setCaneOfByrnaSpikeCaveUsage(int $normal = 0x04, int $half = 0x02, int $quarter = 0x01) : self {
		$this->write(0x18016B, pack('C*', $normal, $half, $quarter));

		return $this;
	}

	/**
	 * Set Cane of Byrna Cave and Misery Mire spike room Cape usage
	 *
	 * @param int $normal normal magic usage
	 * @param int $half half magic usage
	 * @param int $quarter quarter magic usage
	 *
	 * @return $this
	 */
	public function setCapeSpikeCaveUsage(int $normal = 0x04, int $half = 0x08, int $quarter = 0x10) : self {
		$this->write(0x18016E, pack('C*', $normal, $half, $quarter));

		return $this;
	}

	/**
	 * Set mode for HUD clock
	 *
	 * @param string $mode off|stopwatch|countdown-stop|countdown-continue
	 * @param bool $restart wether to restart the timer
	 *
	 * @return $this;
	 */
	public function setClockMode(string $mode = 'off', bool $restart = false) : self {
		switch ($mode) {
			case 'stopwatch':
				$bytes = [0x02, 0x01];
				break;
			case 'countdown-ohko':
				$bytes = [0x01, 0x02];
				$restart = true;
				break;
			case 'countdown-continue':
				$bytes = [0x01, 0x01];
				break;
			case 'countdown-stop':
				$bytes = [0x01, 0x00];
				break;
			case 'off':
			default:
				$bytes = [0x00, 0x00];
				break;
		}

		$bytes = array_merge($bytes, [$restart ? 0x01 : 0x00]);

		$this->write(0x180190, pack('C*', ...$bytes));

		return $this;
	}

	/**
	 * Set starting time for HUD clock
	 *
	 * @param int $seconds time in seconds
	 *
	 * @return $this;
	 */
	public function setStartingTime(int $seconds = 0) : self {
		$this->write(0x18020C, pack('l*', $seconds * 60));

		return $this;
	}

	/**
	 * Set time adjustment for collecting Red Clock Item
	 *
	 * @param int $seconds time in seconds
	 *
	 * @return $this;
	 */
	public function setRedClock(int $seconds = 0) : self {
		$this->write(0x180200, pack('l*', $seconds * 60));

		return $this;
	}

	/**
	 * Set time adjustment for collecting Blue Clock Item
	 *
	 * @param int $seconds time in seconds
	 *
	 * @return $this;
	 */
	public function setBlueClock(int $seconds = 0) : self {
		$this->write(0x180204, pack('l*', $seconds * 60));

		return $this;
	}

	/**
	 * Set time adjustment for collecting Green Clock Item
	 *
	 * @param int $seconds time in seconds
	 *
	 * @return $this;
	 */
	public function setGreenClock(int $seconds = 0) : self {
		$this->write(0x180208, pack('l*', $seconds * 60));

		return $this;
	}

	/**
	 * Set the starting Max Arrows
	 *
	 * @param int $max
	 *
	 * @return $this
	 */
	public function setMaxArrows(int $max = 30) : self {
		$this->write(0x180035, pack('C', $max));

		return $this;
	}

	/**
	 * Set the Digging Game Rng
	 *
	 * @param int $digs
	 *
	 * @return $this
	 */
	public function setDiggingGameRng(int $digs = 15) : self {
		$this->write(0x180020, pack('C', $digs));
		$this->write(0xEFD95, pack('C', $digs));

		return $this;
	}

	/**
	 * Set the starting Max Bombs
	 *
	 * @param int $max
	 *
	 * @return $this
	 */
	public function setMaxBombs(int $max = 10) : self {
		$this->write(0x180034, pack('C', $max));

		return $this;
	}

	/**
	 * Set values to fill for Capacity Upgrades
	 * currently only 4 things: Bomb5, Bomb10, Arrow5, Arrow10
	 *
	 * @param array $fills array of values to fill in
	 *
	 * @return $this
	 */
	public function setCapacityUpgradeFills(array $fills) : self {
		$this->write(0x180080, pack('C*', ...array_slice($fills, 0, 4)));

		return $this;
	}

	/**
	 * Set values to fill for Health/Magic fills from Bottles
	 * currently only 2 things: Health, Magic
	 *
	 * @param array $fills array of values to fill in [health (0xA0 default), magic (0x80 default)]
	 *
	 * @return $this
	 */
	public function setBottleFills(array $fills) : self {
		$this->write(0x180084, pack('C*', ...array_slice($fills, 0, 2)));

		return $this;
	}

	/**
	 * Set the number of goal items to collect
	 *
	 * @param int $goal
	 *
	 * @return $this
	 */
	public function setGoalRequiredCount(int $goal = 0) : self {
		$this->write(0x180167, pack('C', $goal));

		return $this;
	}

	/**
	 * Set the goal item icon
	 *
	 * @param string $goal_icon
	 *
	 * @return $this
	 */
	public function setGoalIcon(string $goal_icon = 'triforce') : self {
		switch ($goal_icon) {
			case 'triforce':
				$byte = pack('S*', 0x280E);
				break;
			case 'star':
			default:
				$byte = pack('S*', 0x280D);
				break;
		}
		$this->write(0x180165, $byte);

		return $this;
	}

	/**
	 * Set Progressive Sword limit and item after limit is reached
	 *
	 * @param int $limit max number to receive
	 * @param int $item item byte to collect once limit is collected
	 *
	 * @return $this
	 */
	public function setLimitProgressiveSword(int $limit = 4, int $item = 0x36) : self {
		$this->write(0x180090, pack('C*', $limit, $item));

		return $this;
	}

	/**
	 * Set Progressive Shield limit and item after limit is reached
	 *
	 * @param int $limit max number to receive
	 * @param int $item item byte to collect once limit is collected
	 *
	 * @return $this
	 */
	public function setLimitProgressiveShield(int $limit = 3, int $item = 0x36) : self {
		$this->write(0x180092, pack('C*', $limit, $item));

		return $this;
	}

	/**
	 * Set Progressive Armor limit and item after limit is reached
	 *
	 * @param int $limit max number to receive
	 * @param int $item item byte to collect once limit is collected
	 *
	 * @return $this
	 */
	public function setLimitProgressiveArmor(int $limit = 2, int $item = 0x36) : self {
		$this->write(0x180094, pack('C*', $limit, $item));

		return $this;
	}

	/**
	 * Set Bottle limit and item after limit is reached
	 *
	 * @param int $limit max number to receive
	 * @param int $item item byte to collect once limit is collected
	 *
	 * @return $this
	 */
	public function setLimitBottle(int $limit = 4, int $item = 0x36) : self {
		$this->write(0x180096, pack('C*', $limit, $item));

		return $this;
	}

	/**
	 * Set Ganon to Invincible. 'dungeons' will require all dungeon bosses are dead to be able to damage Ganon.
	 *
	 * @param string $setting
	 *
	 * @return $this
	 */
	public function setGanonInvincible(string $setting = 'no') : self {
		switch ($setting) {
			case 'crystals':
				$byte = pack('C*', 0x03);
				break;
			case 'dungeons':
				$byte = pack('C*', 0x02);
				break;
			case 'yes':
				$byte = pack('C*', 0x01);
				break;
			case 'no':
			default:
				$byte = pack('C*', 0x00);
				break;
		}
		$this->write(0x18003E, $byte);

		return $this;
	}


	/**
	 * Set the opening Uncle text to a custom value
	 *
	 * @param string $string Uncle text can be 3 lines of 14 characters each
	 *
	 * @return $this
	 */
	public function setUncleTextString(string $string) : self {
		$offset = 0x180500;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the opening Ganon 1 text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setGanon1TextString(string $string) : self {
		$offset = 0x180600;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the opening Ganon 2 text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setGanon2TextString(string $string) : self {
		$offset = 0x180700;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the opening Ganon 1 text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setGanon1InvincibleTextString(string $string) : self {
		$offset = 0x181100;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the opening Ganon 2 text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setGanon2InvincibleTextString(string $string) : self {
		$offset = 0x181200;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}


	/**
	 * Set the Triforce text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setTriforceTextString(string $string) : self {
		$offset = 0x180400;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Blind text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setBlindTextString(string $string) : self {
		$offset = 0x180800;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Tavern Man text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setTavernManTextString(string $string) : self {
		$offset = 0x180C00;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set Sahasrahla before item collection text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setSahasrahla1TextString(string $string) : self {
		$offset = 0x180A00;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set Sahasrahla after item collection text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setSahasrahla2TextString(string $string) : self {
		$offset = 0x180B00;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set Bomb Shop before crystals 5 & 6 text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setBombShop1TextString(string $string) : self {
		$offset = 0x180E00;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set Bomb Shop after crystals 5 & 6 text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setBombShop2TextString(string $string) : self {
		$offset = 0x180D00;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Pyramid Fairy text to a custom value
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setPyramidFairyTextString(string $string) : self {
		$offset = 0x180900;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Altar text to a custom value
	 *
	 * @param string $string Altar text can be 3 lines of 14 characters each
	 *
	 * @return $this
	 */
	public function setPedestalTextbox(string $string) : self {
		$offset = 0x180300;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Bombos Tablet (no upgraded sword) text to a custom value
	 *
	 * @param string $string Bombos text can be 3 lines of 14 characters each
	 *
	 * @return $this
	 */
	public function setBombosTextbox(string $string) : self {
		$offset = 0x181000;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Ether Tablet (no upgraded sword) text to a custom value
	 *
	 * @param string $string Ether text can be 3 lines of 14 characters each
	 *
	 * @return $this
	 */
	public function setEtherTextbox(string $string) : self {
		$offset = 0x180F00;

		$converter = new Dialog;
		foreach ($converter->convertDialog($string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the King's Return credits to a custom value
	 * Original: the return of the king
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setKingsReturnCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 22), 22, ' ', STR_PAD_BOTH);
		$offset = 0x76928;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Sanctuary credits to a custom value
	 * Original: the loyal priest
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setSanctuaryCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 16), 16, ' ', STR_PAD_BOTH);
		$offset = 0x76964;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Kakariko Town credits to a custom value
	 * Original: sahasralah's homecoming
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setKakarikoTownCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 23), 23, ' ', STR_PAD_BOTH);
		$offset = 0x76997;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Desert Palace credits to a custom value
	 * Original: vultures rule the desert
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setDesertPalaceCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 24), 24, ' ', STR_PAD_BOTH);
		$offset = 0x769D4;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Mountain Tower credits to a custom value
	 * Original: the bully makes a friend
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setMountainTowerCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 24), 24, ' ', STR_PAD_BOTH);
		$offset = 0x76A12;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Links House credits to a custom value
	 * Original: your uncle recovers
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setLinksHouseCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 19), 19, ' ', STR_PAD_BOTH);
		$offset = 0x76A52;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Zora credits text to a custom value
	 * Original: finger webs for sale
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setZoraCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 20), 20, ' ', STR_PAD_BOTH);
		$offset = 0x76A85;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Magic Shop credits text to a custom value
	 * Original: the witch and assistant
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setMagicShopCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 23), 23, ' ', STR_PAD_BOTH);
		$offset = 0x76AC5;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Woodsmans Hut credits text to a custom value
	 * Original: twin lumberjacks
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setWoodsmansHutCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 16), 16, ' ', STR_PAD_BOTH);
		$offset = 0x76AFC;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Flute Boy credits to a custom value
	 * Original: ocarina boy plays again
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setFluteBoyCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 23), 23, ' ', STR_PAD_BOTH);
		$offset = 0x76B34;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Wishing Well credits to a custom value
	 * Original: venus. queen of faeries
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setWishingWellCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 23), 23, ' ', STR_PAD_BOTH);
		$offset = 0x76B71;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Swordsmiths credits to a custom value
	 * Original: the dwarven swordsmiths
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setSwordsmithsCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 23), 23, ' ', STR_PAD_BOTH);
		$offset = 0x76BAC;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Bug-Catching Kid credits to a custom value
	 * Original: the bug-catching kid
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setBugCatchingKidCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 20), 20, ' ', STR_PAD_BOTH);
		$offset = 0x76BDF;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Death Mountain credits to a custom value
	 * Original: the lost old man
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setDeathMountainCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 16), 16, ' ', STR_PAD_BOTH);
		$offset = 0x76C19;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Lost Woods credits to a custom value
	 * Original: the forest thief
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setLostWoodsCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 16), 16, ' ', STR_PAD_BOTH);
		$offset = 0x76C51;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Set the Altar credits to a custom value
	 * Original: and the master sword
	 *
	 * @param string $string
	 *
	 * @return $this
	 */
	public function setAltarCredits(string $string) : self {
		$write_string = str_pad(substr($string, 0, 20), 20, ' ', STR_PAD_BOTH);
		$offset = 0x76C81;
		foreach ($this->convertCredits($write_string) as $byte) {
			$this->write($offset++, pack('C', $byte));
		}

		return $this;
	}

	/**
	 * Enable/Disable the predefined ROM debug mode: Starts after Zelda is saved with all items. No chests are open.
	 *
	 * @return $this
	 */
	public function setDebugMode($enable = true) : self {
		$this->write(0x65B88, pack('S*', $enable ? 0xEAEA : 0x21F0));
		$this->write(0x65B91, pack('S*', $enable ? 0xEAEA : 0x18D0));

		return $this;
	}

	/**
	 * Enable/Disable the SRAM Trace function
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setSRAMTrace($enable = true) : self {
		$this->write(0x180030, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Set the single RNG Item table. These items will only get collected by player once per game.
	 *
	 * @param ItemCollection $items
	 *
	 * @return $this
	 */
	public function setSingleRNGTable(ItemCollection $items) : self {
		$bytes = $items->map(function($item) {
			return $item->getBytes()[0];
		});

		$this->write(0x182000, pack('C*', ...$bytes));
		$this->write(0x18207F, pack('C*', count($bytes)));

		return $this;
	}

	/**
	 * Set the multi RNG Item table. These items can be collected multiple times per game.
	 *
	 * @param ItemCollection $items
	 *
	 * @return $this
	 */
	public function setMultiRNGTable(ItemCollection $items) : self {
		$bytes = $items->map(function($item) {
			return $item->getBytes()[0];
		});

		$this->write(0x182080, pack('C*', ...$bytes));
		$this->write(0x1820FF, pack('C*', count($bytes)));

		return $this;
	}

	/**
	 * Set the Seed Type
	 *
	 * @param string $setting name
	 *
	 * @return $this
	 */
	public function setRandomizerSeedType(string $setting) : self {
		switch ($setting) {
			case 'OverworldGlitches':
				$byte = 0x02;
				break;
			case 'MajorGlitches':
				$byte = 0x01;
				break;
			case 'off':
				$byte = 0xFF;
				break;
			case 'NoMajorGlitches':
			default:
				$byte = 0x00;
		}

		$this->write(0x180210, pack('C', $byte));

		return $this;
	}

	/**
	 * Set the Game Type
	 *
	 * @param string $setting name
	 *
	 * @return $this
	 */
	public function setGameType(string $setting) : self {
		switch ($setting) {
			case 'Plandomizer':
				$byte = 0x01;
				break;
			case 'other':
				$byte = 0xFF;
				break;
			case 'Randomizer':
			default:
				$byte = 0x00;
		}

		$this->write(0x180211, pack('C', $byte));

		return $this;
	}

	/**
	 * Set the Plandomizer Author
	 *
	 * @param string $name name of author
	 *
	 * @return $this
	 */
	public function setPlandomizerAuthor(string $name) : self {
		$this->write(0x180220, substr($name, 0, 31));

		return $this;
	}

	/**
	 * Set the Tournament Type
	 *
	 * @param string $setting name
	 *
	 * @return $this
	 */
	public function setTournamentType(string $setting) : self {
		switch ($setting) {
			case 'standard':
				$bytes = [0x01, 0x00];
				break;
			case 'none':
			default:
				$bytes = [0x00, 0x01];
		}

		$this->write(0x180213, pack('C*', ...$bytes));

		return $this;
	}

	/**
	 * Removes Shield from Uncle by moving the tiles for shield to his head and replaces them with his head.
	 *
	 * @return $this
	 */
	public function removeUnclesShield() : self {
		$this->write(0x6D253, pack('C*', 0x00, 0x00, 0xf6, 0xff, 0x00, 0x0E));
		$this->write(0x6D25B, pack('C*', 0x00, 0x00, 0xf6, 0xff, 0x00, 0x0E));
		$this->write(0x6D283, pack('C*', 0x00, 0x00, 0xf6, 0xff, 0x00, 0x0E));
		$this->write(0x6D28B, pack('C*', 0x00, 0x00, 0xf7, 0xff, 0x00, 0x0E));
		$this->write(0x6D2CB, pack('C*', 0x00, 0x00, 0xf6, 0xff, 0x02, 0x0E));
		$this->write(0x6D2FB, pack('C*', 0x00, 0x00, 0xf7, 0xff, 0x02, 0x0E));
		$this->write(0x6D313, pack('C*', 0x00, 0x00, 0xe4, 0xff, 0x08, 0x0E));

		return $this;
	}

	/**
	 * Removes Sword from Uncle by moving the tiles for sword to his head and replaces them with his head.
	 *
	 * @return $this
	 */
	public function removeUnclesSword() : self {
		$this->write(0x6D263, pack('C*', 0x00, 0x00, 0xf6, 0xff, 0x00, 0x0E));
		$this->write(0x6D26B, pack('C*', 0x00, 0x00, 0xf6, 0xff, 0x00, 0x0E));
		$this->write(0x6D293, pack('C*', 0x00, 0x00, 0xf6, 0xff, 0x00, 0x0E));
		$this->write(0x6D29B, pack('C*', 0x00, 0x00, 0xf7, 0xff, 0x00, 0x0E));
		$this->write(0x6D2B3, pack('C*', 0x00, 0x00, 0xf6, 0xff, 0x02, 0x0E));
		$this->write(0x6D2BB, pack('C*', 0x00, 0x00, 0xf6, 0xff, 0x02, 0x0E));
		$this->write(0x6D2E3, pack('C*', 0x00, 0x00, 0xf7, 0xff, 0x02, 0x0E));
		$this->write(0x6D2EB, pack('C*', 0x00, 0x00, 0xf7, 0xff, 0x02, 0x0E));
		$this->write(0x6D31B, pack('C*', 0x00, 0x00, 0xe4, 0xff, 0x08, 0x0E));
		$this->write(0x6D323, pack('C*', 0x00, 0x00, 0xe4, 0xff, 0x08, 0x0E));

		return $this;
	}

	/**
	 * Set the sprite that spawns when a stunned Enemy is killed
	 *
	 * @param int $sprite id of sprite to drop (0xD9 green rupee)
	 *
	 * @return $this
	 */
	public function setStunnedSpritePrize(int $sprite = 0xD9) : self {
		$this->write(0x37993, pack('C*', $sprite));

		return $this;
	}

	/**
	 * Set the sprite that spawns when powdered sprite that usually spawns a faerie is powdered.
	 *
	 * @param int $sprite id of sprite to drop
	 *
	 * @return $this
	 */
	public function setPowderedSpriteFairyPrize(int $sprite = 0xE3) : self {
		$this->write(0x36DD0, pack('C*', $sprite));

		return $this;
	}

	/**
	 * Set pull tree prizes
	 *
	 * @param int $low id of sprite to drop (0xD9 green rupee)
	 * @param int $mid id of sprite to drop (0xDA blue rupee)
	 * @param int $high id of sprite to drop (0xDB red rupee)
	 *
	 * @return $this
	 */
	public function setPullTreePrizes(int $low = 0xD9, int $mid = 0xDA, int $high = 0xDB) : self {
		$this->write(0xEFBD4, pack('C*', $low, $mid, $high));

		return $this;
	}


	/**
	 * Set rupee crab, first and final prizes
	 *
	 * @param int $main id of sprite to drop (0xD9 green rupee)
	 * @param int $final id of sprite to drop (0xDB red rupee)
	 *
	 * @return $this
	 */
	public function setRupeeCrabPrizes(int $main = 0xD9, int $final = 0xDB) : self {
		$this->write(0x329C8, pack('C*', $main));
		$this->write(0x329C4, pack('C*', $final));

		return $this;
	}

	/**
	 * Set fish save prize
	 *
	 * @param int $prize id of sprite to drop (0xDB red rupee)
	 *
	 * @return $this
	 */
	public function setFishSavePrize(int $prize = 0xDB) : self {
		$this->write(0xE82CC, pack('C*', $prize));

		return $this;
	}

	/**
	 * Set Overworld bonk prizes
	 *
	 * @param array $prizes ids of sprites to drop (0x03 empty)
	 *
	 * @return $this
	 */
	public function setOverworldBonkPrizes(array $prize = []) : self {
		$addresses = [
			0x4CF6C, 0x4CFBA, 0x4CFE0, 0x4CFFB, 0x4D018, 0x4D01B, 0x4D028, 0x4D03C,
			0x4D059, 0x4D07A, 0x4D09E, 0x4D0A8, 0x4D0AB, 0x4D0AE, 0x4D0BE, 0x4D0DD,
			0x4D16A, 0x4D1E5, 0x4D1EE, 0x4D20B, 0x4CBBF, 0x4CBBF, 0x4CC17, 0x4CC1A,
			0x4CC4A, 0x4CC4D, 0x4CC53, 0x4CC69, 0x4CC6F, 0x4CC7C, 0x4CCEF, 0x4CD51,
			0x4CDC0, 0x4CDC3, 0x4CDC6, 0x4CE37, 0x4D2DE, 0x4D32F, 0x4D355, 0x4D367,
			0x4D384, 0x4D387, 0x4D397, 0x4D39E, 0x4D3AB, 0x4D3AE, 0x4D3D1, 0x4D3D7,
			0x4D3F8, 0x4D416, 0x4D420, 0x4D423, 0x4D42D, 0x4D449, 0x4D48C, 0x4D4D9,
			0x4D4DC, 0x4D4E3, 0x4D504, 0x4D507, 0x4D55E, 0x4D56A,
		];

		foreach ($addresses as $address) {
			$item = array_pop($prize);
			$this->write($address, pack('C*', $item ?? 0x03));
		}

		return $this;
	}

	/**
	 * Adjust some settings for hard mode
	 *
	 * @param int $level how hard to make it, higher should be harder
	 *
	 * @return $this
	 */
	public function setHardMode(int $level = 0) : self {
		$this->setBelowGanonChest(false);
		$this->setCaneOfByrnaSpikeCaveUsage();
		$this->setCapeSpikeCaveUsage();
		$this->setByrnaCaveSpikeDamage(0x08);

		switch ($level) {
			case 0:
				// Cape magic
				$this->write(0x3ADA7, pack('C*', 0x04, 0x08, 0x10));
				// Bryna magic amount used per "cycle"
				$this->write(0x45C42, pack('C*', 0x04, 0x02, 0x01));
				$this->setPowderedSpriteFairyPrize(0xE3);
				$this->setBottleFills([0xA0, 0x80]);
				$this->setShopBlueShieldCost(50);
				$this->setShopRedShieldCost(500);
				$this->setCatchableFairies(true);
				$this->setCatchableBees(true);

				$this->setRupoorValue(0);

				break;
			case 1:
				$this->write(0x3ADA7, pack('C*', 0x02, 0x02, 0x02));
				$this->write(0x45C42, pack('C*', 0x08, 0x08, 0x08));
				$this->setPowderedSpriteFairyPrize(0xD8); // 1 heart
				$this->setBottleFills([0x28, 0x40]); // 5 hearts, 1/2 magic refills
				$this->setShopBlueShieldCost(100);
				$this->setShopRedShieldCost(999);
				$this->setCatchableFairies(false);
				$this->setCatchableBees(true);

				$this->setRupoorValue(10);

				break;
			case 2:
				$this->write(0x3ADA7, pack('C*', 0x01, 0x01, 0x01));
				$this->write(0x45C42, pack('C*', 0x10, 0x10, 0x10));
				$this->setPowderedSpriteFairyPrize(0x79); // Bees
				$this->setBottleFills([0x08, 0x20]); // 1 heart, 1/4 magic refills
				$this->setShopBlueShieldCost(9990);
				$this->setShopRedShieldCost(9990);
				$this->setCatchableFairies(false);
				$this->setCatchableBees(true);

				$this->setRupoorValue(20);

				break;
		}

		return $this;
	}

	/**
	 * Set the cost of Blue Shields in shops (shop sprite: 0x08).
	 *
	 * @param int $cost
	 *
	 * @return $this
	 */
	public function setShopBlueShieldCost(int $cost = 50) : self {
		$cost_digits = str_split($cost);
		if ($cost > 999) {
			$this->write(0xF73D2, pack('C*', 0xFC, 0xFF)); // reposition gfx
			$this->write(0xF73DA, pack('C*', 0x04, 0x00)); // reposition gfx
			$this->write(0xF73E2, pack('C*', 0x0C, 0x00)); // reposition gfx
			$this->write(0xF73D6, pack('C*', 0x3C)); // -
			$this->write(0xF73DE, pack('C*', 0x3C)); // -
			$this->write(0xF73E6, pack('C*', 0x3C)); // -
		} else if ($cost > 99) {
			$this->write(0xF73D2, pack('C*', 0xFC, 0xFF)); // reposition gfx
			$this->write(0xF73DA, pack('C*', 0x04, 0x00)); // reposition gfx
			$this->write(0xF73E2, pack('C*', 0x0C, 0x00)); // reposition gfx
			$this->write(0xF73D6, pack('C*', static::$digit_gfx[$cost_digits[0]]));
			$this->write(0xF73DE, pack('C*', static::$digit_gfx[$cost_digits[1]]));
			$this->write(0xF73E6, pack('C*', static::$digit_gfx[$cost_digits[2]]));
		} else {
			$this->write(0xF73D2, pack('C*', 0x00, 0x00)); // reposition gfx
			$this->write(0xF73DA, pack('C*', 0x00, 0x00)); // reposition gfx
			$this->write(0xF73E2, pack('C*', 0x08, 0x00)); // reposition gfx
			$this->write(0xF73D6, pack('C*', static::$digit_gfx[$cost_digits[0]]));
			$this->write(0xF73DE, pack('C*', static::$digit_gfx[$cost_digits[0]]));
			$this->write(0xF73E6, pack('C*', static::$digit_gfx[$cost_digits[1]]));
		}

		$this->write(0xF7201, pack('C*', $cost >> 8));
		$this->write(0xF71FF, pack('C*', $cost & 0xFF));

		return $this;
	}

	/**
	 * Set the cost of Red Shields in shops (shop sprite: ??).
	 *
	 * @param int $cost
	 *
	 * @return $this
	 */
	public function setShopRedShieldCost(int $cost = 500) : self {
		$cost_digits = str_split($cost);
		if ($cost > 999) {
			$this->write(0xF73FA, pack('C*', 0xFC, 0xFF)); // reposition gfx
			$this->write(0xF7402, pack('C*', 0x04, 0x00)); // reposition gfx
			$this->write(0xF740A, pack('C*', 0x0C, 0x00)); // reposition gfx
			$this->write(0xF73FE, pack('C*', 0x3C)); // -
			$this->write(0xF7406, pack('C*', 0x3C)); // -
			$this->write(0xF740E, pack('C*', 0x3C)); // -
		} else if ($cost > 99) {
			$this->write(0xF73FA, pack('C*', 0xFC, 0xFF)); // reposition gfx
			$this->write(0xF7402, pack('C*', 0x04, 0x00)); // reposition gfx
			$this->write(0xF740A, pack('C*', 0x0C, 0x00)); // reposition gfx
			$this->write(0xF73FE, pack('C*', static::$digit_gfx[$cost_digits[0]]));
			$this->write(0xF7406, pack('C*', static::$digit_gfx[$cost_digits[1]]));
			$this->write(0xF740E, pack('C*', static::$digit_gfx[$cost_digits[2]]));
		} else {
			$this->write(0xF73FA, pack('C*', 0x00, 0x00)); // reposition gfx
			$this->write(0xF7402, pack('C*', 0x00, 0x00)); // reposition gfx
			$this->write(0xF740A, pack('C*', 0x08, 0x00)); // reposition gfx
			$this->write(0xF73FE, pack('C*', static::$digit_gfx[$cost_digits[0]]));
			$this->write(0xF7406, pack('C*', static::$digit_gfx[$cost_digits[0]]));
			$this->write(0xF740E, pack('C*', static::$digit_gfx[$cost_digits[1]]));
		}

		$this->write(0xF7241, pack('C*', $cost >> 8));
		$this->write(0xF723F, pack('C*', $cost & 0xFF));

		return $this;
	}


	/**
	 * Set Smithy Quick Item Give mode. I.E. just gives an item if you rescue him with no sword bogarting
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setSmithyQuickItemGive(bool $enable = true) : self {
		$this->write(0x180029, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Set Pyramid Fountain to have 2 chests
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setPyramidFairyChests(bool $enable = true) : self {
		$this->write(0x1FC16, $enable
			? pack('C*', 0xB1, 0xC6, 0xF9, 0xC9, 0xC6, 0xF9)
			: pack('C*', 0xA8, 0xB8, 0x3D, 0xD0, 0xB8, 0x3D));

		return $this;
	}

	/**
	 * Enable Hammer activates tablets
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setHammerTablet(bool $enable = false) : self {
		$this->write(0x180044, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable/Disable ability to bug net catch Fairy
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setCatchableFairies(bool $enable = true) : self {
		$this->write(0x34FD6, pack('C*', $enable ? 0xF0 : 0x80));

		return $this;
	}

	/**
	 * Enable/Disable ability to bug net catch Bee (also makes them attack you?)
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setCatchableBees(bool $enable = true) : self {
		$this->write(0xF5D73, pack('C*', $enable ? 0xF0 : 0x80));
		$this->write(0xF5F10, pack('C*', $enable ? 0xF0 : 0x80));

		return $this;
	}

	/**
	 * Set space directly below Ganon to have a chest
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setBelowGanonChest(bool $enable = true) : self {
		// convert telepathic tile to chest and place it
		$this->write(0x50563, $enable ? pack('C*', 0xC5, 0x76) : pack('C*', 0x3F, 0x14));
		// lock door to under ganon
		$this->write(0x50599, $enable ? pack('C*', 0x38) : pack('C*', 0x00));
		// set dungeon secret to this chest
		$this->write(0xE9A5, $enable ? pack('C*', 0x10, 0x00, 0x58) : pack('C*', 0x7E, 0x00, 0x24));

		return $this;
	}

	/**
	 * Place 2 chests in Waterfall of Wishing Fairy.
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setWishingWellChests(bool $enable = false) : self {
		// set item table to proper room
		$this->write(0xE9AE, $enable ? pack('C*', 0x14, 0x01) : pack('C*', 0x05, 0x00));
		$this->write(0xE9CF, $enable ? pack('C*', 0x14, 0x01) : pack('C*', 0x3D, 0x01));

		// room 276 remodel
		$this->write(0x1F714, $enable
			? base64_decode(
				"4QAQrA0pmgFYmA8RsWH8TYEg2gIs4WH8voFhsWJU2gL9jYNE4WL9HoMxpckxpGkxwCJNpGkxxvlJxvkQmaBcmaILmGAN6MBV6MALk" .
				"gBzmGD+aQCYo2H+a4H+q4WpyGH+roH/aQLYo2L/a4P/K4fJyGL/LoP+oQCqIWH+poH/IQLKIWL/JoO7I/rDI/q7K/rDK/q7U/rDU/" .
				"qwoD2YE8CYUsCIAGCQAGDoAGDwAGCYysDYysDYE8DYUsD8vYX9HYf/////8P+ALmEOgQ7//w==")
			: base64_decode(
				"4QAQrA0pmgFYmA8RsGH8TQEg0gL8vQUs4WH8voFhsGJU0gL9jQP9HQdE4WL9HoMxpckxpGkxwCJNpGkouD1QuD0QmaBcmaILmGAN4" .
				"cBV4cALkgBzmGD+aQCYo2H+a4H+q4WpyGH+roH/aQLYo2L/a4P/K4fJyGL/LoP+oQCqIWH+poH/IQLKIWL/JoO7I/rDI/q7K/rDK/" .
				"q7U/rDU/qwoD2YE8CYUsCIAGCQAGDoAGDwAGCYysDYysDYE8DYUsD/////8P+ALmEOgQ7//w=="));

		return $this;
	}

	/**
	 * Enable/Disable Waterfall of Wishing Fairy's ability to upgrade items.
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setWishingWellUpgrade(bool $enable = false) : self {
		$this->write(0x348DE, pack('C*', $enable ? 0x2A : 0x0C));
		$this->write(0x348FE, pack('C*', $enable ? 0x05 : 0x04));

		return $this;
	}

	/**
	 * Set Game in Open Mode. (Post rain state with Escape undone)
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setOpenMode(bool $enable = true) : self {
		$this->write(0x180032, pack('C*', $enable ? 0x01 : 0x00));
		$this->setSewersLampCone(!$enable);
		$this->setLightWorldLampCone(false);
		$this->setDarkWorldLampCone(false);

		return $this;
	}

	/**
	 * Enable maps to show crystals on overworld map
	 *
	 * @param bool $require_map switch on or off
	 *
	 * @return $this
	 */
	public function setMapMode(bool $require_map = false) : self {
		$this->write(0x18003B, pack('C*', $require_map ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable compass to show dungeon count
	 *
	 * @param bool $show_count switch on or off
	 *
	 * @return $this
	 */
	public function setCompassMode(bool $show_count = false) : self {
		$this->write(0x18003C, pack('C*', $show_count ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable text box to show with free roaming items
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setFreeItemTextMode(bool $enable = true) : self {
		$this->write(0x18016A, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable swordless mode
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setSwordlessMode(bool $enable = false) : self {
		$this->write(0x18003F, pack('C*', $enable ? 0x01 : 0x00)); // Hammer Ganon
		$this->write(0x180040, pack('C*', $enable ? 0x01 : 0x00)); // Open Curtains
		$this->write(0x180041, pack('C*', $enable ? 0x01 : 0x00)); // Swordless Medallions
		$this->write(0x180043, pack('C*', $enable ? 0xFF : 0x00)); // set Link's starting sword 0xFF is taken sword

		$this->setHammerTablet($enable);

		return $this;
	}

	/**
	 * Enable lampless light cone in Sewers
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setSewersLampCone(bool $enable = true) : self {
		$this->write(0x180038, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable lampless light cone in Light World Dungeons
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setLightWorldLampCone(bool $enable = true) : self {
		$this->write(0x180039, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable lampless light cone in Dark World Dungeons
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setDarkWorldLampCone(bool $enable = true) : self {
		$this->write(0x18003A, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable/Disable the ROM Hack that doesn't leave Link stranded in DW
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setMirrorlessSaveAndQuitToLightWorld(bool $enable = true) : self {
		$this->write(0x1800A0, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable/Disable ability to Save and Quit from Boss room after item collection.
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setSaveAndQuitFromBossRoom(bool $enable = false) : self {
		$this->write(0x180042, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable/Disable the ROM Hack that drains the Swamp on transition
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setSwampWaterLevel(bool $enable = true) : self {
		$this->write(0x1800A1, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable/Disable the ROM Hack that sends Link to Real DW on death in DW dungeon if AG1 is not dead
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setPreAgahnimDarkWorldDeathInDungeon(bool $enable = true) : self {
		$this->write(0x1800A2, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable/Disable World on Agahnim Death
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setWorldOnAgahnimDeath(bool $enable = true) : self {
		$this->write(0x1800A3, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Enable/Disable locking Hyrule Castle Door to AG1 during escape
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function setLockAgahnimDoorInEscape(bool $enable = true) : self {
		$this->write(0x180169, pack('C*', $enable ? 0x01 : 0x00));

		return $this;
	}

	/**
	 * Set the Ganon Warp Phase and Agahnim BB mode
	 *
	 * @param string $setting name
	 *
	 * @return $this
	 */
	public function setGanonAgahnimRng(string $setting = 'table') : self {
		switch ($setting) {
			case 'none':
				$byte = 0x01;
				break;
			case 'vanilla':
			case 'table':
			default:
				$byte = 0x00;
		}

		$this->write(0x180086, pack('C', $byte));

		return $this;
	}

	/**
	 * Write the seed identifier
	 *
	 * @param string $seed identifier for this seed
	 *
	 * @return $this
	 */
	public function setSeedString(string $seed) : self {
		$this->write(0x7FC0, substr($seed, 0, 21));

		return $this;
	}

	/**
	 * Write a hash of Logic version in ROM.
	 *
	 * @param array $byte byte array that relates to logic
	 *
	 * @return $this
	 */
	public function writeRandomizerLogicHash(array $bytes) : self {
		$this->write(0x187F00, pack('C*', ...$bytes));

		return $this;
	}

	/**
	 * Write a block of data to RNG Block in ROM.
	 *
	 * @param callable $random prng byte generator
	 *
	 * @return $this
	 */
	public function writeRNGBlock(callable $random) : self {
		$string = '';
		for ($i = 0; $i < 1024; $i++) {
			$string .= pack('C*', $random());
		}
		$this->write(0x178000, $string);

		return $this;
	}

	/**
	 * set the flags byte in ROM
	 *
	 * dgGe mutT
	 * d - Nonstandard Dungeon Configuration (Not Map/Compass/BigKey/SmallKeys in same quantity as vanilla)
	 * g - Requires Minor Glitches (Fake flippers, bomb jumps, etc)
	 * G - Requires Major Glitches (OW YBA/Clips, etc)
	 * e - Requires EG
	 *
	 * m - Contains Multiples of Major Items
	 * u - Contains Unreachable Items
	 * t - Minor Trolling (Swapped around levers, etc)
	 * T - Major Trolling (Forced-guess softlocks, impossible seed, etc)
	 *
	 * @param int $flags byte of flags to set
	 *
	 * @return $this
	 */
	public function setWarningFlags(int $flags) : self {
		$this->write(0x180212, pack('C*', $flags));

		return $this;
	}

	/**
	 * Mute all audio tracks.
	 *
	 * @param bool $enable switch on or off
	 *
	 * @return $this
	 */
	public function muteMusic(bool $enable = true) : self {
		$tracks_by_volume = [
			0x00 => [0xD373B, 0xD375B, 0xD90F8],
			0x14 => [0xDA710, 0xDA7A4, 0xDA7BB, 0xDA7D2],
			0x3C => [0xD5954, 0xD653B, 0xDA736, 0xDA752, 0xDA772, 0xDA792],
			0x50 => [0xD5B47, 0xD5B5E],
			0x5A => [0xD4306],
			0x64 => [0xD6878, 0xD6883, 0xD6E48, 0xD6E76, 0xD6EFB, 0xD6F2D, 0xDA211, 0xDA35B, 0xDA37B, 0xDA38E,
				0xDA39F, 0xDA5C3, 0xDA691, 0xDA6A8, 0xDA6DF],
			0x78 => [0xD2349, 0xD3F45, 0xD42EB, 0xD48B9, 0xD48FF, 0xD543F, 0xD5817, 0xD5957, 0xD5ACB, 0xD5AE8,
				0xD5B4A, 0xDA5DE, 0xDA608, 0xDA635, 0xDA662, 0xDA71F, 0xDA7AF, 0xDA7C6, 0xDA7DD],
			0x82 => [0xD2F00, 0xDA3D5],
			0xA0 => [0xD249C, 0xD24CD, 0xD2C09, 0xD2C53, 0xD2CAF, 0xD2CEB, 0xD2D91, 0xD2EE6, 0xD38ED, 0xD3C91,
				0xD3CD3, 0xD3CE8, 0xD3F0C, 0xD3F82, 0xD405F, 0xD4139, 0xD4198, 0xD41D5, 0xD41F6, 0xD422B, 0xD4270,
				0xD42B1, 0xD4334, 0xD4371, 0xD43A6, 0xD43DB, 0xD441E, 0xD4597, 0xD4B3C, 0xD4BAB, 0xD4C03, 0xD4C53,
				0xD4C7F, 0xD4D9C, 0xD5424, 0xD65D2, 0xD664F, 0xD6698, 0xD66FF, 0xD6985, 0xD6C5C, 0xD6C6F, 0xD6C8E,
				0xD6CB4, 0xD6D7D, 0xD827D, 0xD960C, 0xD9828, 0xDA233, 0xDA3A2, 0xDA49E, 0xDA72B, 0xDA745, 0xDA765,
				0xDA785, 0xDABF6, 0xDAC0D, 0xDAEBE, 0xDAFAC],
			0xAA => [0xD9A02, 0xD9BD6],
			0xB4 => [0xD21CD, 0xD2279, 0xD2E66, 0xD2E70, 0xD2EAB, 0xD3B97, 0xD3BAC, 0xD3BE8, 0xD3C0D, 0xD3C39,
				0xD3C68, 0xD3C9F, 0xD3CBC, 0xD401E, 0xD4290, 0xD443E, 0xD456F, 0xD47D3, 0xD4D43, 0xD4DCC, 0xD4EBA,
				0xD4F0B, 0xD4FE5, 0xD5012, 0xD54BC, 0xD54D5, 0xD54F0, 0xD5509, 0xD57D8, 0xD59B9, 0xD5A2F, 0xD5AEB,
				0xD5E5E, 0xD5FE9, 0xD658F, 0xD674A, 0xD6827, 0xD69D6, 0xD69F5, 0xD6A05, 0xD6AE9, 0xD6DCF, 0xD6E20,
				0xD6ECB, 0xD71D4, 0xD71E6, 0xD7203, 0xD721E, 0xD8724, 0xD8732, 0xD9652, 0xD9698, 0xD9CBC, 0xD9DC0,
				0xD9E49, 0xDAA68, 0xDAA77, 0xDAA88, 0xDAA99, 0xDAF04],
			0x8C => [0xD1D28, 0xD1D41, 0xD1D5C, 0xD1D77, 0xD1EEE, 0xD311D, 0xD31D1, 0xD4148, 0xD5543, 0xD5B6F,
				0xD65B3, 0xD6760, 0xD6B6B, 0xD6DF6, 0xD6E0D, 0xD73A1, 0xD814C, 0xD825D, 0xD82BE, 0xD8340, 0xD8394,
				0xD842C, 0xD8796, 0xD8903, 0xD892A, 0xD91E8, 0xD922B, 0xD92E0, 0xD937E, 0xD93C1, 0xDA958, 0xDA971,
				0xDA98C, 0xDA9A7],
			0xC8 => [0xD1D92, 0xD1DBD, 0xD1DEB, 0xD1F5D, 0xD1F9F, 0xD1FBD, 0xD1FDC, 0xD1FEA, 0xD20CA, 0xD21BB,
				0xD22C9, 0xD2754, 0xD284C, 0xD2866, 0xD2887, 0xD28A0, 0xD28BA, 0xD28DB, 0xD28F4, 0xD293E, 0xD2BF3,
				0xD2C1F, 0xD2C69, 0xD2CA1, 0xD2CC5, 0xD2D05, 0xD2D73, 0xD2DAF, 0xD2E3D, 0xD2F36, 0xD2F46, 0xD2F6F,
				0xD2FCF, 0xD2FDF, 0xD302B, 0xD3086, 0xD3099, 0xD30A5, 0xD30CD, 0xD30F6, 0xD3154, 0xD3184, 0xD333A,
				0xD33D9, 0xD349F, 0xD354A, 0xD35E5, 0xD3624, 0xD363C, 0xD3672, 0xD3691, 0xD36B4, 0xD36C6, 0xD3724,
				0xD3767, 0xD38CB, 0xD3B1D, 0xD3B2F, 0xD3B55, 0xD3B70, 0xD3B81, 0xD3BBF, 0xD3D34, 0xD3D55, 0xD3D6E,
				0xD3DC6, 0xD3E04, 0xD3E38, 0xD3F65, 0xD3FA6, 0xD404F, 0xD4087, 0xD417A, 0xD41A0, 0xD425C, 0xD4319,
				0xD433C, 0xD43EF, 0xD440C, 0xD4452, 0xD4494, 0xD44B5, 0xD4512, 0xD45D1, 0xD45EF, 0xD4682, 0xD46C3,
				0xD483C, 0xD4848, 0xD4855, 0xD4862, 0xD486F, 0xD487C, 0xD4A1C, 0xD4A3B, 0xD4A60, 0xD4B27, 0xD4C7A,
				0xD4D12, 0xD4D81, 0xD4E90, 0xD4ED6, 0xD4EE2, 0xD5005, 0xD502E, 0xD503C, 0xD5081, 0xD51B1, 0xD51C7,
				0xD51CF, 0xD51EF, 0xD520C, 0xD5214, 0xD5231, 0xD5257, 0xD526D, 0xD5275, 0xD52AF, 0xD52BD, 0xD52CD,
				0xD52DB, 0xD549C, 0xD5801, 0xD58A4, 0xD5A68, 0xD5A7F, 0xD5C12, 0xD5D71, 0xD5E10, 0xD5E9A, 0xD5F8B,
				0xD5FA4, 0xD651A, 0xD6542, 0xD65ED, 0xD661D, 0xD66D7, 0xD6776, 0xD68BD, 0xD68E5, 0xD6956, 0xD6973,
				0xD69A8, 0xD6A51, 0xD6A86, 0xD6B96, 0xD6C3E, 0xD6D4A, 0xD6E9C, 0xD6F80, 0xD717E, 0xD7190, 0xD71B9,
				0xD811D, 0xD8139, 0xD816B, 0xD818A, 0xD819E, 0xD81BE, 0xD829C, 0xD82E1, 0xD8306, 0xD830E, 0xD835E,
				0xD83AB, 0xD83CA, 0xD83F0, 0xD83F8, 0xD844B, 0xD8479, 0xD849E, 0xD84CB, 0xD84EB, 0xD84F3, 0xD854A,
				0xD8573, 0xD859D, 0xD85B4, 0xD85CE, 0xD862A, 0xD8681, 0xD87E3, 0xD87FF, 0xD887B, 0xD88C6, 0xD88E3,
				0xD8944, 0xD897B, 0xD8C97, 0xD8CA4, 0xD8CB3, 0xD8CC2, 0xD8CD1, 0xD8D01, 0xD917B, 0xD918C, 0xD919A,
				0xD91B5, 0xD91D0, 0xD91DD, 0xD9220, 0xD9273, 0xD9284, 0xD9292, 0xD92AD, 0xD92C8, 0xD92D5, 0xD9311,
				0xD9322, 0xD9330, 0xD934B, 0xD9366, 0xD9373, 0xD93B6, 0xD97A6, 0xD97C2, 0xD97DC, 0xD97FB, 0xD9811,
				0xD98FF, 0xD996F, 0xD99A8, 0xD99D5, 0xD9A30, 0xD9A4E, 0xD9A6B, 0xD9A88, 0xD9AF7, 0xD9B1D, 0xD9B43,
				0xD9B7C, 0xD9BA9, 0xD9C84, 0xD9C8D, 0xD9CAC, 0xD9CE8, 0xD9CF3, 0xD9CFD, 0xD9D46, 0xDA35E, 0xDA37E,
				0xDA391, 0xDA478, 0xDA4C3, 0xDA4D7, 0xDA4F6, 0xDA515, 0xDA6E2, 0xDA9C2, 0xDA9ED, 0xDAA1B, 0xDAA57,
				0xDABAF, 0xDABC9, 0xDABE2, 0xDAC28, 0xDAC46, 0xDAC63, 0xDACB8, 0xDACEC, 0xDAD08, 0xDAD25, 0xDAD42,
				0xDAD5F, 0xDAE17, 0xDAE34, 0xDAE51, 0xDAF2E, 0xDAF55, 0xDAF6B, 0xDAF81, 0xDB14F, 0xDB16B, 0xDB180,
				0xDB195, 0xDB1AA],
			0xD2 => [0xD2B88, 0xD364A, 0xD369F, 0xD3747],
			0xDC => [0xD213F, 0xD2174, 0xD229E, 0xD2426, 0xD4731, 0xD4753, 0xD4774, 0xD4795, 0xD47B6, 0xD4AA5,
				0xD4AE4, 0xD4B96, 0xD4CA5, 0xD5477, 0xD5A3D, 0xD6566, 0xD672C, 0xD67C0, 0xD69B8, 0xD6AB1, 0xD6C05,
				0xD6DB3, 0xD71AB, 0xD8E2D, 0xD8F0D, 0xD94E0, 0xD9544, 0xD95A8, 0xD9982, 0xD9B56, 0xDA694, 0xDA6AB,
				0xDAE88, 0xDAEC8, 0xDAEE6, 0xDB1BF],
			0xE6 => [0xD210A, 0xD22DC, 0xD2447, 0xD5A4D, 0xD5DDC, 0xDA251, 0xDA26C],
			0xF0 => [0xD945E, 0xD967D, 0xD96C2, 0xD9C95, 0xD9EE6, 0xDA5C6],
			0xFA => [0xD2047, 0xD24C2, 0xD24EC, 0xD25A4, 0xD3DAA, 0xD51A8, 0xD51E6, 0xD524E, 0xD529E, 0xD6045,
				0xD81DE, 0xD821E, 0xD94AA, 0xD9A9E, 0xD9AE4, 0xDA289],
			0xFF => [0xD2085, 0xD21C5, 0xD5F28],
		];

		foreach ($tracks_by_volume as $original_volume => $tracks) {
			$byte = pack('C*', $enable ? 0x00 : $original_volume);
			foreach ($tracks as $address) {
				$this->write($address, $byte);
			}
		}

		return $this;
	}

	/**
	 * Apply a patch to the ROM
	 *
	 * @param array $patch patch to apply
	 *
	 * @return $this
	 *
	 **/
	public function applyPatch(array $patch) : self {
		foreach ($patch as $part) {
			foreach ($part as $address => $data) {
				$this->write($address, pack('C*', ...array_values($data)));
			}
		}

		return $this;
	}

	/**
	 * rummage table
	 *
	 * @return $this
	 */
	public function rummageTable() : self {
		$swip = [];
		$swap = [];

		$idat = array_values(unpack('C*', base64_decode(
			"MgAAVQAAcQAAqAAAEwEAqYAAFgEAFgEANwAANoAACwEAc4AAZwAAfgAAWIAAWAAAVwAAVwAAHwAAfgAAnoAAdwAAFAEAuQAAdAA" .
			"AuAAABAEA/gAAdQAADAEAaAAAhQAAAwEAFAEALgAALQEAswAAPwAAXwAArgAAhwAACAEABgEAHAEACgEAqgAAJ4AAJwAAWQAA2w" .
			"AA2wAA3AAAywAAZQAARIAARQAAtgAAJIAAtwAAtwAA1gAAFAAA1QAA1QAA1QAA1QAABAAAOgAAKgAAKgAAGoAAGgAAGgAACgAAa" .
			"gAAagAAKwAAGQAAGQAACQAAwgAAogAAwQAAw4AAwwAA0QAAswAADQEADQEAEgAA+AAA+AAABQEABQEABQEAFwEALwAALwAALwAA" .
			"LwAALwAAKAAARgAANAAANQAAdgAAdgAAZgAA0AAA4AAAewAAewAAewAAewAAfAAAfAAAfAAAfAAAfQAAiwAAjIAAjAAAjAAAjAA" .
			"AjQAAnQAAnQAAnQAAnQAAHAAAHAAAHAAAWwAAPQAAPQAAPQAATQAAgAAAcgAAHQEAHQEAHQEAHQEAHQEAHgEAHgEAHgEAHgEA7w" .
			"AA7wAA7wAA7wAA7wAA/wAA/wAAJAEAIwEAIwEAIwEAIwEAIAEAPAAAPAAAPAAAPAAAEQAAEQAAEQAA"
		)));

		$data = $this->read(0xE96C, 504);
		foreach ($data as $i => $v) {
			$data[$i] = ($v == 0) ? $idat[$i] : $v;
		}
		$data = array_chunk($data, 3);
		foreach ($data as $chunk) {
			$swip[($chunk[0] << 8) + ($chunk[1] | 0x80)][] = $chunk;
		}

		for ($i = 0; $i < count($data); ++$i) {
			$swip = mt_shuffle($swip);
			$swap[] = array_shift($swip[0]);
			if (!count($swip[0])) {
				unset($swip[0]);
			}
		}

		$this->write(0xE96C, pack('C*', ...array_flatten($swap)));

		return $this;
	}

	/**
	 * Save the changes to this output file
	 *
	 * @param string $output_location location on the filesystem to write the new ROM.
	 *
	 * @return bool
	 */
	public function save(string $output_location) : bool {
		return copy($this->tmp_file, $output_location);
	}

	/**
	 * Write packed data at the given offset
	 *
	 * @param int $offset location in the ROM to begin writing
	 * @param string $data data to write to the ROM
	 * @param bool $log write this write to the log
	 *
	 * @return $this
	 */
	public function write(int $offset, string $data, bool $log = true) : self {
		if ($log) {
			$this->write_log[] = [$offset => array_values(unpack('C*', $data))];
		}
		fseek($this->rom, $offset);
		fwrite($this->rom, $data);

		return $this;
	}

	/**
	 * Get the array of bytes written in the order they were written to the rom
	 *
	 * @return array
	 */
	public function getWriteLog() : array {
		return $this->write_log;
	}

	/**
	 * Read data from the ROM file into an array
	 *
	 * @param int $offset location in the ROM to begin reading
	 * @param int $length data to read
	 * // TODO: this should probably always be an array, or a new Bytes object
	 * @return array
	 */
	public function read(int $offset, int $length = 1) {
		fseek($this->rom, $offset);
		$unpacked = unpack('C*', fread($this->rom, $length));
		return count($unpacked) == 1 ? $unpacked[1] : array_values($unpacked);
	}

	/**
	 * Object destruction magic method
	 *
	 * @return void
	 */
	public function __destruct() {
		if ($this->rom) {
			fclose($this->rom);
		}
		unlink($this->tmp_file);
	}

	/**
	 * Convert string to byte array for Credits that can be written to ROM
	 *
	 * @param string $string string to convert
	 *
	 * @return array
	 */
	public function convertCredits(string $string) : array {
		$byte_array = [];
		foreach (str_split(strtolower($string)) as $char) {
			$byte_array[] = $this->charToCreditsHex($char);
		}

		return $byte_array;
	}

	/**
	 * Convert character to byte for ROM in Credits Sequence
	 *
	 * @param string $string character to convert
	 *
	 * @return int
	 */
	private function charToCreditsHex(string $char) : int {
		if (preg_match('/[a-z]/', $char)) {
			return ord($char) - 0x47;
		}
		switch ($char) {
			case ' ': return 0x9F;
			case ',': return 0x37;
			case '.': return 0x37;
			case '-': return 0x36;
			case "'": return 0x35;
			default: return 0x9F;
		}
	}
}
