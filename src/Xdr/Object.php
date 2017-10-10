<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/10
 * Time: 上午11:49
 */

namespace Irelance\Mozjs52\Xdr;


trait Object
{
    public function xdrObjectExtra($objectType)
    {
        $result = 'xdr';
        switch ($objectType) {
            case 'CK_RegexpObject':
            case 'CK_JSFunction':
            case 'CK_JSObject':
                $result .= $objectType;
                break;
            default:
                $result .= 'CK_Not';
                break;
        }
        return $this->$result();
    }

    protected function xdrCK_Not()
    {
        return [];
    }

    protected function xdrCK_RegexpObject()
    {
        return [
            'regexp' => $this->XDRAtom(),
            'flagsword' => $this->todec(),
        ];
    }

    protected function xdrCK_JSFunction()
    {
        $funEnclosingScopeIndex = $this->todec();
        $this->XDRInterpretedFunction();//todo get the information
        return [
            'funEnclosingScopeIndex' => $funEnclosingScopeIndex,
        ];
    }

    public function xdrCK_JSObject()
    {
        $isArray = $this->todec();
        if ($isArray) {
            $initialized = $this->todec();
            for ($i = 0; $i < $initialized; $i++) {
                $val = $this->xdrConst();
            }
            $copyOnWrite = $this->todec();
        } else {
            $nproperties = $this->todec();
            for ($i = 0; $i < $nproperties; $i++) {
                $key = $this->xdrConst();
                $val = $this->xdrConst();
            }
            $isSingleton = $this->todec();
        }
        return [];
    }

}
