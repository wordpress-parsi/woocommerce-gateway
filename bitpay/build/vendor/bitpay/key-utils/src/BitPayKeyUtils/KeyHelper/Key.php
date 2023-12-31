<?php

namespace BitPayVendor\BitPayKeyUtils\KeyHelper;

use BitPayVendor\BitPayKeyUtils\Util\Point;
/**
 * Abstract object that is used for Public, Private, and SIN keys
 *
 * @package Bitcore
 */
abstract class Key extends Point implements KeyInterface
{
    /**
     * @var string
     */
    protected $hex;
    /**
     * @var string
     */
    protected $dec;
    /**
     * @var string
     */
    protected $id;
    /**
     * @param string $id
     */
    public function __construct($id = null)
    {
        $this->id = $id;
    }
    /**
     * Returns a new instance of self.
     *
     * @param string $id
     * @return KeyInterface
     */
    public static function create($id = null)
    {
        $class = \get_called_class();
        return new $class($id);
    }
    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
    /**
     * @return string
     */
    public function getHex()
    {
        return $this->hex;
    }
    /**
     * @return string
     */
    public function getDec()
    {
        return $this->dec;
    }
    /**
     * @inheritdoc
     */
    public function serialize()
    {
        return \serialize(array($this->id, $this->x, $this->y, $this->hex, $this->dec));
    }
    /**
     * @inheritdoc
     */
    public function unserialize($data)
    {
        list($this->id, $this->x, $this->y, $this->hex, $this->dec) = \unserialize($data);
    }
    /**
     * @return array
     */
    public function __serialize() : array
    {
        return [$this->id, $this->x, $this->y, $this->hex, $this->dec];
    }
    /**
     * @param array $data
     */
    public function __unserialize(array $data) : void
    {
        list($this->id, $this->x, $this->y, $this->hex, $this->dec) = $data;
    }
    /**
     * @return boolean
     */
    public function isGenerated()
    {
        return !empty($this->hex);
    }
}
