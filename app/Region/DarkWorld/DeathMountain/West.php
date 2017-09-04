<?php namespace ALttP\Region\DarkWorld\DeathMountain;

use ALttP\Item;
use ALttP\Location;
use ALttP\Region;
use ALttP\Support\LocationCollection;
use ALttP\World;

/**
 * Dark World Region and it's Locations contained within
 */
class West extends Region {
	protected $name = 'Dark World';

	/**
	 * Create a new Dark World Region and initalize it's locations
	 *
	 * @param World $world World this Region is part of
	 *
	 * @return void
	 */
	public function __construct(World $world) {
		parent::__construct($world);

		$this->locations = new LocationCollection([
			new Location\Chest("[cave-055] Spike cave", 0xEA8B, null, $this),
		]);
	}

	/**
	 * Set Locations to have Items like the vanilla game.
	 *
	 * @return $this
	 */
	public function setVanilla() {
		$this->locations["[cave-055] Spike cave"]->setItem(Item::get('CaneOfByrna'));

		return $this;
	}

	/**
	 * Initalize the requirements for Entry and Completetion of the Region as well as access to all Locations contained
	 * within for No Major Glitches
	 *
	 * @return $this
	 */
	public function initNoMajorGlitches() {
		$this->locations["[cave-055] Spike cave"]->setRequirements(function($locations, $items) {
			return $items->has('MoonPearl') && $items->has('Hammer') && $items->canLiftRocks()
				&& ($items->has('Cape') || $items->has('CaneOfByrna'))
				&& $items->canExtendMagic()
				&& $this->world->getRegion('West Death Mountain')->canEnter($locations, $items);
		});

		return $this;
	}

	/**
	 * Initalize the requirements for Entry and Completetion of the Region as well as access to all Locations contained
	 * within for MajorGlitches Mode
	 *
	 * @return $this
	 */
	public function initMajorGlitches() {
		// @TODO: This should account for 2x YBA
		$this->locations["[cave-055] Spike cave"]->setRequirements(function($locations, $items) {
			return $items->has('Hammer') && $items->canLiftRocks()
				&& ($items->has('MoonPearl') || ($items->hasABottle() && $items->has('PegasusBoots')))
				&& ($items->has('Cape') || $items->has('CaneOfByrna'))
				&& $items->canExtendMagic()
				&& $this->world->getRegion('West Death Mountain')->canEnter($locations, $items);
		});

		return $this;
	}
}
