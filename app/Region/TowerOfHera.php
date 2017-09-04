<?php namespace ALttP\Region;

use ALttP\Item;
use ALttP\Location;
use ALttP\Region;
use ALttP\Support\LocationCollection;
use ALttP\World;

/**
 * Tower of Hera Region and it's Locations contained within
 */
class TowerOfHera extends Region {
	protected $name = 'Tower Of Hera';
	public $music_addresses = [
		0x155C5,
		0x1107A,
		0x10B8C,
	];

	protected $region_items = [
		'BigKey',
		'BigKeyP3',
		'Compass',
		'CompassP3',
		'Key',
		'KeyP3',
		'Map',
		'MapP3',
	];

	/**
	 * Create a new Tower of Hera Region and initalize it's locations
	 *
	 * @param World $world World this Region is part of
	 *
	 * @return void
	 */
	public function __construct(World $world) {
		parent::__construct($world);

		$this->locations = new LocationCollection([
			new Location\Chest("[dungeon-L3-1F] Tower of Hera - first floor", 0xE9E6, null, $this),
			new Location\Standing\HeraBasement("[dungeon-L3-1F] Tower of Hera - freestanding key", 0x180162, null, $this),
			new Location\Chest("[dungeon-L3-2F] Tower of Hera - Entrance", 0xE9AD, null, $this),
			new Location\Chest("[dungeon-L3-4F] Tower of Hera - 4F [small chest]", 0xE9FB, null, $this),
			new Location\BigChest("[dungeon-L3-4F] Tower of Hera - big chest", 0xE9F8, null, $this),
			new Location\Drop("Heart Container - Moldorm", 0x180152, null, $this),

			new Location\Prize\Pendant("Tower of Hera Pendant", [null, 0x120A5, 0x53F0A, 0x53F0B, 0x18005A, 0x18007A, 0xC706], null, $this),
		]);

		$this->prize_location = $this->locations["Tower of Hera Pendant"];
	}

	/**
	 * Set Locations to have Items like the vanilla game.
	 *
	 * @return $this
	 */
	public function setVanilla() {
		$this->locations["[dungeon-L3-1F] Tower of Hera - first floor"]->setItem(Item::get('BigKeyP3'));
		$this->locations["[dungeon-L3-1F] Tower of Hera - freestanding key"]->setItem(Item::get('KeyP3'));
		$this->locations["[dungeon-L3-2F] Tower of Hera - Entrance"]->setItem(Item::get('MapP3'));
		$this->locations["[dungeon-L3-4F] Tower of Hera - 4F [small chest]"]->setItem(Item::get('CompassP3'));
		$this->locations["[dungeon-L3-4F] Tower of Hera - big chest"]->setItem(Item::get('MoonPearl'));
		$this->locations["Heart Container - Moldorm"]->setItem(Item::get('BossHeartContainer'));

		$this->locations["Tower of Hera Pendant"]->setItem(Item::get('PendantOfPower'));

		return $this;
	}

	/**
	 * Initalize the requirements for Entry and Completetion of the Region as well as access to all Locations contained
	 * within for No Major Glitches
	 *
	 * @return $this
	 */
	public function initNoMajorGlitches() {
		$this->locations["[dungeon-L3-1F] Tower of Hera - first floor"]->setRequirements(function($locations, $items) {
			return $items->canLightTorches()
				&& $items->has('KeyP3');
		})->setFillRules(function($item, $locations, $items) {
			return $item != Item::get('KeyP3');
		});

		$this->locations["[dungeon-L3-4F] Tower of Hera - 4F [small chest]"]->setRequirements(function($locations, $items) {
			return $items->has('BigKeyP3');
		})->setFillRules(function($item, $locations, $items) {
			return $item != Item::get('BigKeyP3');
		});

		$this->locations["[dungeon-L3-4F] Tower of Hera - big chest"]->setRequirements(function($locations, $items) {
			return $items->has('BigKeyP3');
		})->setFillRules(function($item, $locations, $items) {
			return $item != Item::get('BigKeyP3');
		});

		$this->can_complete = function($locations, $items) {
			return $this->canEnter($locations, $items)
				&& ($items->hasSword() || $items->has('Hammer'))
				&& $items->has('BigKeyP3');
		};

		$this->locations["Heart Container - Moldorm"]->setRequirements($this->can_complete)
			->setFillRules(function($item, $locations, $items) {
				if (!$this->world->config('region.bossNormalLocation', true)
					&& ($item instanceof Item\Key || $item instanceof Item\BigKey
						|| $item instanceof Item\Map || $item instanceof Item\Compass)) {
					return false;
				}

				return $item != Item::get('BigKeyP3');
			});

		$this->can_enter = function($locations, $items) {
			return ($items->has('MagicMirror') || ($items->has('Hookshot') && $items->has('Hammer')))
				&& $this->world->getRegion('West Death Mountain')->canEnter($locations, $items);
		};

		$this->prize_location->setRequirements($this->can_complete);

		return $this;
	}

	/**
	 * Initalize the requirements for Entry and Completetion of the Region as well as access to all Locations contained
	 * within for MajorGlitches Mode.
	 *
	 * @return $this
	 */
	public function initMajorGlitches() {
		$this->initOverworldGlitches();

		$main = function($locations, $items) {
			return $items->has('PegasusBoots')
				|| (($items->has('MagicMirror') || ($items->has('Hookshot') && $items->has('Hammer')))
					&& $this->world->getRegion('West Death Mountain')->canEnter($locations, $items));
		};

		$mire = function($locations, $items) {
			return (($locations->itemInLocations(Item::get('BigKeyD6'), [
						"[dungeon-D6-B1] Misery Mire - compass",
						"[dungeon-D6-B1] Misery Mire - big key",
					]) && $items->has('KeyD6', 2))
				|| $items->has('KeyD6', 3))
			&& $this->world->getRegion('Misery Mire')->canEnter($locations, $items);
		};

		$this->locations["[dungeon-L3-1F] Tower of Hera - first floor"]->setRequirements(function($locations, $items) {
			return $items->canLightTorches() && $items->has('KeyP3');
		})->setFillRules(function($item, $locations, $items) {
			return $item != Item::get('KeyP3');
		});

		$this->locations["[dungeon-L3-4F] Tower of Hera - 4F [small chest]"]->setRequirements(function($locations, $items) use ($main, $mire) {
			return ($main($locations, $items) && $items->has('BigKeyP3'))
				|| $mire($locations, $items);
		});

		$this->locations["[dungeon-L3-4F] Tower of Hera - big chest"]->setRequirements(function($locations, $items) use ($main, $mire) {
			return ($main($locations, $items) && $items->has('BigKeyP3'))
				|| ($mire($locations, $items) && ($items->has('BigKeyP3') || $items->has('BigKeyP6')));
		});

		$this->locations["Heart Container - Moldorm"]->setRequirements(function($locations, $items) use ($main, $mire) {
			return (($main($locations, $items) && $items->has('BigKeyP3'))
					|| $mire($locations, $items))
				&& ($items->hasSword() || $items->has('Hammer'));
		});

		$this->can_complete = function($locations, $items) use ($main, $mire) {
			return ((($main($locations, $items) && $items->has('BigKeyP3'))
					|| ($mire($locations, $items) && ($items->has('BigKeyP3') || $items->has('BigKeyP6'))))
				&& ($items->hasSword() || $items->has('Hammer')))
			|| ($locations["[dungeon-L3-4F] Tower of Hera - big chest"]->canAccess($items)
				&& $locations["Heart Container - Arrghus"]->canAccess($items));
		};

		$this->can_enter = function($locations, $items) use ($main, $mire) {
			return $main($locations, $items)
				|| $mire($locations, $items);
		};

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
			return $items->has('PegasusBoots')
				|| (($items->has('MagicMirror') || ($items->has('Hookshot') && $items->has('Hammer')))
					&& $this->world->getRegion('West Death Mountain')->canEnter($locations, $items));
		};

		return $this;
	}
}
