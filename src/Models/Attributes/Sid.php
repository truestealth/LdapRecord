<?php

namespace LdapRecord\Models\Attributes;

use InvalidArgumentException;
use LdapRecord\Utilities;

class Sid
{
    /**
     * The string SID value.
     *
     * @var string
     */
    protected $value;

    /**
     * Determines if the specified SID is valid.
     *
     * @param string $sid
     *
     * @return bool
     */
    public static function isValid($sid)
    {
        return Utilities::isValidSid($sid);
    }

    /**
     * Constructor.
     *
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     */
    public function __construct($value)
    {
        if (static::isValid($value)) {
            $this->value = $value;
        } elseif ($value = $this->binarySidToString($value)) {
            $this->value = $value;
        } else {
            throw new InvalidArgumentException('Invalid Binary / String SID.');
        }
    }

    /**
     * Returns the string value of the SID.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getValue();
    }

    /**
     * Returns the string value of the SID.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns the binary variant of the SID.
     *
     * @return string
     */
    public function getBinary()
    {
        $sid = explode('-', ltrim($this->value, 'S-'));

        $level = (int) array_shift($sid);

        $authority = (int) array_shift($sid);

        $subAuthorities = array_map('intval', $sid);

        $params = array_merge(
            ['C2xxNV*', $level, count($subAuthorities), $authority],
            $subAuthorities
        );

        return call_user_func_array('pack', $params);
    }

    /**
     * Returns the string variant of a binary SID.
     *
     * @param string $binary
     *
     * @return string|null
     */
    protected function binarySidToString($binary)
    {
        // Revision - 8bit unsigned int (C1)
        // Count - 8bit unsigned int (C1)
        // 2 null bytes
        // ID - 32bit unsigned long, big-endian order
        $sid = @unpack('C1rev/C1count/x2/N1id', $binary);

        if (! isset($sid['id']) || ! isset($sid['rev'])) {
            return;
        }

        $revisionLevel = $sid['rev'];

        $identifierAuthority = $sid['id'];

        $subs = isset($sid['count']) ? $sid['count'] : 0;

        $sidHex = $subs ? bin2hex($binary) : '';

        $subAuthorities = [];

        // The sub-authorities depend on the count, so only get as
        // many as the count, regardless of data beyond it.
        for ($i = 0; $i < $subs; $i++) {
            $data = implode(array_reverse(
                str_split(
                    substr($sidHex, 16 + ($i * 8), 8),
                    2
                )
            ));

            $subAuthorities[] = hexdec($data);
        }

        // Tack on the 'S-' and glue it all together...
        return 'S-'.$revisionLevel.'-'.$identifierAuthority.implode(
            preg_filter('/^/', '-', $subAuthorities)
        );
    }
}
