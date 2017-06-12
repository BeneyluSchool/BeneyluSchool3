<?php
namespace BNS\App\StatisticsBundle\Statistics;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class Indicator
{
    /** @var  string */
    protected $name;

    /** @var  string */
    protected $code;

    /**
     * Indicator constructor.
     * @param string $name
     * @param string $code
     */
    public function __construct($name, $code)
    {
        $this->name = $name;
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Indicator
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return Indicator
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }
}
