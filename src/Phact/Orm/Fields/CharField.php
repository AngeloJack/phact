<?php
/**
 *
 *
 * All rights reserved.
 *
 * @author Okulov Anton
 * @email qantus@mail.ru
 * @version 1.0
 * @company HashStudio
 * @site http://hashstudio.ru
 * @date 13/04/16 08:11
 */

namespace Phact\Orm\Fields;


class CharField extends Field
{
    public function getValue($aliasConfig = null)
    {
        return !is_null($this->_attribute) ? (string)$this->_attribute : null;
    }

    public function _dbPrepareValue($value)
    {
        return (string)$value;
    }
}