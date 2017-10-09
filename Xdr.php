<?php

/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/9/30
 * Time: 上午9:54
 */
trait Xdr
{
    public function getScopeXdrFunctionName($scopeKind)
    {
        $result = 'xdr';
        switch ($scopeKind) {
            case 'Function':
                $result .= 'Function';
                break;
            case 'FunctionBodyVar':
            case 'ParameterExpressionVar':
                $result .= 'Var';
                break;
            case 'Lexical':
            case 'SimpleCatch':
            case 'Catch':
            case 'NamedLambda':
            case 'StrictNamedLambda':
                $result .= 'Lexical';
                break;
            case 'With':
                $result .= 'With';
                break;
            case 'Eval':
            case 'StrictEval':
                $result .= 'Eval';
                break;
            case 'Global':
            case 'NonSyntactic':
                $result .= 'Global';
                break;
            case 'Module':
            default:
                $result .= 'Not';
        }
        return $result;
    }

    public function XDRSizedBindingNames()
    {
        $length = $this->todec();
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $u8 = $this->todec(1);
            $hasAtom = $u8 >> 1;
            if ($hasAtom) {
                $result[] = $this->XDRAtom();
            }
        }
        return $result;
    }

    public function xdrNot()
    {
        return [];
    }

    public function xdrWith()
    {
        return [];
    }

    public function xdrLexical()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'constStart' => $this->todec(),
            'firstFrameSlot' => $this->todec(),
            'nextFrameSlot' => $this->todec()
        ];
    }

    public function xdrFunction()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'needsEnvironment' => $this->todec(1),
            'hasParameterExprs' => $this->todec(1),
            'nonPositionalFormalStart' => $this->todec(2),
            'varStart' => $this->todec(2),
            'nextFrameSlot' => $this->todec(),
        ];
    }

    public function xdrVar()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'needsEnvironment' => $this->todec(1),
            'firstFrameSlot' => $this->todec(),
            'nextFrameSlot' => $this->todec()
        ];
    }

    public function xdrGlobal()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'letStart' => $this->todec(),
            'constStart' => $this->todec(),
        ];
    }

    public function xdrEval()
    {
        return [
            'bindingNames' => $this->XDRSizedBindingNames(),
            'length' => $this->todec(),
        ];
    }


    public function getObjectXdrFunctionName($objectType)
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
        return $result;
    }

    public function xdrCK_Not()
    {
        return [];
    }

    public function xdrCK_RegexpObject()
    {
        return [
            'regexp' => $this->XDRAtom(),
            'flagsword' => $this->todec(),
        ];
    }

    public function XDRLazyScript()
    {
        //XDRLazyScript
        $begin = $this->todec();
        $end = $this->todec();
        $lineno = $this->todec();
        $column = $this->todec();
        $packedFields = $this->todec(8);
        //todo XDRLazyClosedOverBindings for 0 -> lazy->numClosedOverBindings()
        $endOfScopeSentinel = $this->todec(1);
        //todo for 0 -> lazy->numInnerFunctions() XDRInterpretedFunction
        $this->XDRInterpretedFunction();
    }

    public function getLatin1Chars($length)
    {
        $end = $this->parseIndex + $length;
        $atom = '';
        for (; $this->parseIndex < $end; $this->parseIndex++) {
            $atom .= chr($this->bytecodes[$this->parseIndex]);
        }
        return $atom;
    }

    public function getTwoByteChar()
    {
        $char = '\u' . dechex($this->bytecodes[$this->parseIndex + 1]) . dechex($this->bytecodes[$this->parseIndex]);
        $this->parseIndex += 2;
        return $char;
    }

    public function getTwoByteChars($length)
    {
        $atom = '';
        for ($i = 0; $i < $length; $i++) {
            $atom .= $this->getTwoByteChar();
        }
        return json_decode('"' . $atom . '"');
    }

    public function getChars($hasLatin1Chars, $length)
    {
        if ($hasLatin1Chars) {
            $atom = $this->getLatin1Chars($length);
        } else {
            $atom = $this->getTwoByteChars($length);
        }
        return $atom;
    }

    public function XDRAtom()
    {
        $lengthAndEncoding = $this->todec();
        $hasLatin1Chars = $lengthAndEncoding & 1;
        $length = $lengthAndEncoding >> 1;
        return $this->getChars($hasLatin1Chars, $length);
    }

    public function XDRInterpretedFunction()
    {
        $firstword = $this->todec();
        if ($firstword & Kind::_FirstWordFlag['HasAtom']) {
            $this->XDRAtom();
        }
        $flagsword = $this->todec();
        if ($firstword & Kind::_FirstWordFlag['IsLazy']) {
            $this->XDRLazyScript();
        } else {
            $this->XDRScript();
        }
    }

    public function xdrCK_JSFunction()
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

    public function xdrConst()
    {
        $const = [
            'type' => $this->todec(),
        ];
        switch ($const['type']) {
            case 0:
                $const['value'] = $this->todec();
                break;
            case 1:
                $const['value'] = $this->todec(8);
                break;
            case 2:
                $const['value'] = $this->XDRAtom();
                break;
            case 3:
                $const['value'] = true;
                break;
            case 4:
                $const['value'] = false;
                break;
            case 5:
                $const['value'] = null;
                break;
            case 6:
                $object = $this->xdrCK_JSObject();
                $const['value'] = "__OBJECT__";
                break;
            case 7:
                $const['value'] = "__VOID__";
                break;
            case 8:
                $const['value'] = "__HOLE__";
                break;
            default:
                $const['value'] = "__ERROR__";
                break;
        }
        return $const;
    }
}
