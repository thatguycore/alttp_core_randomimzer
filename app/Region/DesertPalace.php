<?php namespace ALttP\Region;

use ALttP\Item;
use ALttP\Location;
use ALttP\Region;
use ALttP\Support\LocationCollection;
use ALttP\World;

/**
 * Desert Palace Region and it's Locations contained within
 */
class DesertPalace extends Region {
	protected $name = 'Desert Palace';
	public $music_addresses = [
		0x1559B,
		0x1559C,
		0x1559D,
		0x1559E,
	];

	protected $region_items = [
		'BigKey',
		'BigKeyP2',
		'Compass',
		'CompassP2',
		'Key',
		'KeyP2',
		'Map',
		'MapP2',
	];

	/**
	 * Create a new Desert Palace Region and initalize it's locations
	 *
	 * @param World $world World this Region is part of
	 *
	 * @return void
	 */
	public function __construct(World $world) {
		parent::__construct($world);

		$this->locations = new LocationCollection([
			new Location\BigChest("[dungeon-L2-B1] Desert Palace - big chest", 0xE98F, null, $this),
			new Location\Chest("[dungeon-L2-B1] Desert Palace - Map room", 0xE9B6, null, $this),
			new Location\Dash("[dungeon-L2-B1] Desert Palace - Small key room", 0x180160, null, $this),
			new Location\Chest("[dungeon-L2-B1] Desert Palace - Big key room", 0xE9C2, null, $this),
			new Location\Chest("[dungeon-L2-B1] Desert Palace - compass room", 0xE9CB, null, $this),
			new Location\Drop("Heart Container - Lanmolas", 0x180151, null, $this),

			new Location\Prize\Pendant("Desert Palace Pendant", [null, 0x1209E, 0x53F1C, 0x53F1D, 0x180053, 0x180078, 0xC6FF], null, $this),
		]);

		$this->prize_location = $this->locations["Desert Palace Pendant"];
	}

	/**
	 * Set Locations to have Items like the vanilla game.
	 *
	 * @return $this
	 */
	public function setVanilla() {
		$this->locations["[dungeon-L2-B1] Desert Palace - big chest"]->setItem(Item::get('PowerGlove'));
		$this->locations["[dungeon-L2-B1] Desert Palace - Map room"]->setItem(Item::get('MapP2'));
		$this->locations["[dungeon-L2-B1] Desert Palace - Small key room"]->setItem(Item::get('KeyP2'));
		$this->locations["[dungeon-L2-B1] Desert Palace - Big key room"]->setItem(Item::get('BigKeyP2'));
		$this->locations["[dungeon-L2-B1] Desert Palace - compass room"]->setItem(Item::get('CompassP2'));
		$this->locations["Heart Container - Lanmolas"]->setItem(Item::get('BossHeartContainer'));

		$this->locations["Desert Palace Pendant"]->setItem(Item::get('PendantOfWisdom'));

		return $this;
	}

	/**
	 * Initalize the requirements for Entry and Completetion of the Region as well as access to all Locations contained
	 * within for No Major Glitches
	 *
	 * @return $this
	 */
	public function initNoMajorGlitches() {
		$this->locations["[dungeon-L2-B1] Desert Palace - big chest"]->setRequirements(function($locations, $items) {
			return $items->has('BigKeyP2');
		})->setFillRules(function($item, $locations, $items) {
			return $item != Item::get('BigKeyP2');
		});

		$this->locations["[dungeon-L2-B1] Desert Palace - Big key room"]->setRequirements(function($locations, $items) {
			return $items->has('KeyP2');
		})->setFillRules(function($item, $locations, $items) {
			return $item != Item::get('KeyP2');
		});

		$this->locations["[dungeon-L2-B1] Desert Palace - compass room"]->setRequirements(function($locations, $items) {
			return $items->has('KeyP2');
		})->setFillRules(function($item, $locations, $items) {
			return $item != Item::get('KeyP2');
		});

		$this->locations["[dungeon-L2-B1] Desert Palace - Small key room"]->setRequirements(function($locations, $items) {
			return $items->has('PegasusBoots');
		});

		$this->can_complete = function($locations, $items) {
			if (in_array(config('game-mode'), ['open', 'swordless']) && !($items->hasSword() || $items->has('Hammer')
					|| $items->canShootArrows() || $items->has('FireRod') || $items->has('IceRod')
					|| $items->has('CaneOfByrna') || $items->has('CaneOfSomaria'))) {
				return false;
			}

			return $this->canEnter($locations, $items)
				&& $items->canLiftRocks() && $items->canLightTorches()
				&& $items->has('BigKeyP2') && $items->has('KeyP2');
		};

		$this->locations["Heart Container - Lanmolas"]->setRequirements($this->can_complete)
			->setFillRules(function($item, $locations, $items) {
				if (!$this->world->config('region.bossNormalLocation', true)
					&& ($item instanceof Item\Key || $item instanceof Item\BigKey
						|| $item instanceof Item\Map || $item instanceof Item\Compass)) {
					return false;
				}

				return !in_array($item, [Item::get('KeyP2'), Item::get('BigKeyP2')]);
			});

		$this->can_enter = function($locations, $items) {
			return $items->has('BookOfMudora')
				|| ($items->has('MagicMirror') && $items->canLiftDarkRocks() && $items->canFly());
		};

		$this->prize_location->setRequirements($this->can_complete);

		return $this;
	}

	/**
	 * Initalize the requirements for Entry and Completetion of the Region as well as access to all Locations contained
	 * within for Overworld Glitches Mode
	 *
	 * @return $this
	 */
	public function initOverworldGlitches() {
		$this->initNoMajorGlitches();

		$this->can_enter = function($locations, $items) {
			return $items->has('BookOfMudora')
				|| $items->has('PegasusBoots')
				|| ($items->has('MagicMirror') && $this->world->getRegion('Mire')->canEnter($locations, $items));
		};

		return $this;
	}
}
