<?php
// PackageBuilder.php

// Product Class ----Sandwich
class Package
{
    public $name;
    public $destinations = [];
    public $moneySaved = [];
    public $dayCount = [];
    public $pickup = [];
    public $transportType = [];
    public $transportCost = [];
    public $details;
    public $image;
}

// Abstract Builder Class
abstract class PackageBuilder
{
    protected $package;
    public function __construct()
    {
        $this->package = new Package(); //creating new package obj and storing it to the package variable //new fresh package

    }
    abstract public function addName($name);
    abstract public function addDestinations($destinations);
    abstract public function addMoneySaved($moneySaved);
    abstract public function addDayCount($dayCount);
    abstract public function addPickup($pickup);
    abstract public function addTransportType($transportType);
    abstract public function addTransportCost($transportCost);
    abstract public function addDetails($details);
    abstract public function addImage($image);
    public function getPackage()
    {
        return $this->package; // Return the final package object
    }
}

// Concrete Builder for Tour Package
class TourPackageBuilder extends PackageBuilder
{
    public function addName($name)
    {
        $this->package->name = $name; //accesing 'name' of package and assigning 
    }
    public function addDestinations($destinations)
    {
        $this->package->destinations = $destinations;
    }
    public function addMoneySaved($moneySaved)
    {
        $this->package->moneySaved = $moneySaved;
    }
    public function addDayCount($dayCount)
    {
        $this->package->dayCount = $dayCount;
    }
    public function addPickup($pickup)
    {
        $this->package->pickup = $pickup;
    }
    public function addTransportType($transportType)
    {
        $this->package->transportType = $transportType;
    }
    public function addTransportCost($transportCost)
    {
        $this->package->transportCost = $transportCost;
    }
    public function addDetails($details)
    {
        $this->package->details = $details;
    }
    public function addImage($image)
    {
        $this->package->image = $image;
    }
}
// Director Class, which controls the building process
class PackageDirector
{
    private $builder;
    public function __construct(PackageBuilder $builder) //parameter must be a class that inherits from PackageBuilder

    {
        $this->builder = $builder;
    }

    //receives a builder like packagebuilder
    //store that builder
    //will use the bulder to build
    
    // Method for building a full package with all details
    public function buildFullPackage($name, $destinations, $moneySaved, $dayCount, $pickup, $transportType, $transportCost, $details, $image)
    {
        $this->builder->addName($name);
        $this->builder->addDestinations($destinations);
        $this->builder->addMoneySaved($moneySaved);
        $this->builder->addDayCount($dayCount);
        $this->builder->addPickup($pickup);
        $this->builder->addTransportType($transportType);
        $this->builder->addTransportCost($transportCost);
        $this->builder->addDetails($details);
        $this->builder->addImage($image);
    }
    
    // Method for building a basic package with minimal details
    public function buildBasicPackage($name, $details, $image)
    {
        $this->builder->addName($name);
        $this->builder->addDestinations([]);
        $this->builder->addMoneySaved([]);
        $this->builder->addDayCount([]);
        $this->builder->addPickup([]);
        $this->builder->addTransportType([]);
        $this->builder->addTransportCost([]);
        $this->builder->addDetails($details);
        $this->builder->addImage($image);
    }
}