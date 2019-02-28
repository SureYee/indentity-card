<?php

namespace Sureyee\IdentityCard;


use Sureyee\AreaDB\AreaDB;

/**
 * Class IdentityCard
 * @package Sureyee\IdentityCard
 */
class IdentityCard
{
    /**
     * @var string idcard
     */
    protected $number;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var int $length 身份证长度
     */
    protected $length;

    /**
     * @var bool
     */
    protected $isValid;

    protected $attributes = [];

    /**
     * @var AreaDB $areaDB
     */
    protected $areaDB;

    /**
     * @var resource $connection SQLITE链接
     */
    protected $connection;

    public function __construct(string $number, array $config = [])
    {
        $this->number = strtoupper($number);
        $this->length = strlen($number);
        $this->config = $config;
        if (!$this->isValid())
            throw new InvalidIdCardException($this->number);
    }

    /**
     * 验证身份证
     * @return bool
     */
    public function isValid()
    {
        return is_null($this->isValid)
            ? $this->regex() && $this->validCode()
            : $this->isValid;
    }

    /**
     * 验证身份证
     * @param $idCard
     * @return bool
     */
    public static function valid($idCard)
    {
        try {
            new self($idCard);
        } catch (InvalidIdCardException $exception) {
            return false;
        }
        return true;
    }

    /**
     * 验证校验码
     */
    protected function validCode()
    {
        // 15位身份证不校验
        if ($this->length === 15) return true;

        if ($this->getCode() === substr($this->number, 17, 1)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获取校验码
     * @return mixed
     */
    protected function getCode()
    {
        $idCardBase = substr($this->number, 0, 17);
        $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];

        $codeList = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
        $checksum = 0;
        for ($i = 0; $i < strlen($idCardBase); $i++) {
            $checksum += substr($idCardBase, $i, 1) * $factor[$i];
        }
        $mod = $checksum % 11;
        return $codeList[$mod];
    }

    /**
     * 正则校验
     * @return false|int
     */
    protected function regex()
    {
        if ($this->length === 18) {
            return preg_match('/^\d{6}(18|19|20)\d{2}(0[1-9]|1[012])(0[1-9]|[12]\d|3[01])\d{3}(\d|X)$/', $this->number);
        } else {
            return preg_match('/^\d{6}\d{2}(0[1-9]|1[012])(0[1-9]|[12]\d|3[01])\d{3}$/', $this->number);
        }
    }

    /**
     * 获取区域信息
     * @param string $glue
     * @return string
     */
    public function getAreaString($glue='')
    {
        return join($glue, $this->getAreaInfo());
    }

    /**
     * 获取区域信息
     * @return array
     */
    public function getAreaInfo()
    {
        return [
            'province' => $this->getProvince(),
            'city' => $this->getCity(),
            'county' => $this->getCounty(),
        ];
    }

    /**
     * 获取省份信息
     * @return string
     */
    public function getProvince()
    {
        return !isset($this->attributes['province'])
            ? $this->attributes['province'] = $this->loadAreaDB()->get($this->getProvinceCode())->name
            : $this->attributes['province'];
    }

    /**
     * 获取城市信息
     * @return string
     */
    public function getCity()
    {
        return !isset($this->attributes['city'])
            ? $this->attributes['city'] = $this->loadAreaDB()->get($this->getCityCode())->name
            : $this->attributes['city'];
    }

    /**
     * 获取郡县信息
     * @return mixed
     */
    public function getCounty()
    {
        return !isset($this->attributes['county'])
            ? $this->attributes['county'] = $this->loadAreaDB()->get($this->getCountyCode())->name
            : $this->attributes['county'];
    }

    /**
     * 获取郡县编码
     * @return bool|string
     */
    public function getCountyCode()
    {
        return substr($this->number, 0, 6);
    }

    /**
     * 获取城市编码
     * @return string
     */
    public function getCityCode()
    {
        return substr($this->number, 0, 4) . '00';
    }

    /**
     * 获取省份编码
     * @return string
     */
    public function getProvinceCode()
    {
        return substr($this->number, 0, 2) . '0000';
    }

    /**
     * 获取生日信息
     * @param string|\Closure $format
     * @return mixed
     */
    public function getBirthday($format = 'Y-m-d')
    {
        $birthday = $this->length === 18
            ? substr($this->number, 6, 8)
            : '19' . substr($this->number, 6, 6);

        $this->attributes['birthday'] = $birthday;

        if (is_string($format)) {
            return date($format, strtotime($birthday));
        }

        if ($format instanceof \Closure) {
            return $format($birthday);
        }

        return $birthday;
    }

    /**
     * 获取性别
     * @return string
     */
    public function getGender()
    {
        if (isset($this->attributes['gender'])) return $this->attributes['gender'];

        if ($this->length === 15) {
            $g = substr($this->number, 15, 1);
        } else {
            $g = substr($this->number, 16, 1);
        }
        $this->attributes['gender'] = ($g % 2 == 0) ? 'F' : 'M';

        return $this->attributes['gender'];
    }

    public function getAge()
    {
        if (isset($this->attributes['age'])) return $this->attributes['age'];
        $today = date_create(date('Ymd'));
        $birthday = date_create($this->getBirthday());

        return $birthday->diff($today)->format('%r%y');
    }

    public function loadAreaDB(): AreaDB
    {
        return is_null($this->areaDB) ? $this->areaDB =  new AreaDB($this->config) : $this->areaDB;
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function __get($key)
    {
        $method = 'get' . ucfirst($key);
        if (method_exists($this, $method))
        {
            return $this->{$method}();
        }
        return null;
    }
}