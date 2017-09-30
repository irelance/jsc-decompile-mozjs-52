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

    public function xdrNot()
    {
    }

    public function xdrWith()
    {
    }

    public function xdrLexical()
    {
        $constStart = $this->todec();
        echo 'constStart:', $constStart, $this->CRLF;
        $firstFrameSlot = $this->todec();
        echo 'firstFrameSlot:', $firstFrameSlot, $this->CRLF;
        $nextFrameSlot = $this->todec();
        echo 'nextFrameSlot:', $nextFrameSlot, $this->CRLF;
    }

    public function xdrFunction()
    {
        $needsEnvironment = $this->todec(1);
        echo 'needsEnvironment:', $needsEnvironment, $this->CRLF;
        $hasParameterExprs = $this->todec(1);
        echo 'hasParameterExprs:', $hasParameterExprs, $this->CRLF;
        //data
        $nonPositionalFormalStart = $this->todec(2);
        echo 'nonPositionalFormalStart:', $nonPositionalFormalStart, $this->CRLF;
        $varStart = $this->todec(2);
        echo 'varStart:', $varStart, $this->CRLF;
        $nextFrameSlot = $this->todec();
        echo 'nextFrameSlot:', $nextFrameSlot, $this->CRLF;
    }

    public function xdrVar()
    {
        $needsEnvironment = $this->todec(1);
        echo 'needsEnvironment:', $needsEnvironment, $this->CRLF;
        $firstFrameSlot = $this->todec();
        echo 'firstFrameSlot:', $firstFrameSlot, $this->CRLF;
        $nextFrameSlot = $this->todec();
        echo 'nextFrameSlot:', $nextFrameSlot, $this->CRLF;
    }

    public function xdrGlobal()
    {
        //data
        $letStart = $this->todec();
        echo 'letStart:', $letStart, $this->CRLF;
        $constStart = $this->todec();
        echo 'constStart:', $constStart, $this->CRLF;
    }

    public function xdrEval()
    {
        //binding name
        $length = $this->todec();
        echo 'length:', $length, $this->CRLF;
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
    }

    public function xdrCK_RegexpObject()
    {
    }

    public function XDRLazyScript()
    {
        //XDRLazyScript
        $begin = $this->todec();
        $end = $this->todec();
        $lineno = $this->todec();
        $column = $this->todec();
        $packedFields = $this->todec(8);
        //XDRLazyClosedOverBindings for 0 -> lazy->numClosedOverBindings()
        $endOfScopeSentinel = $this->todec(1);
        //for 0 -> lazy->numInnerFunctions() XDRInterpretedFunction
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

    public function getChars($hasLatin1Chars,$length){
        if ($hasLatin1Chars) {
            $atom = $this->getLatin1Chars($length);
        } else {
            //todo 2byte char
            $atom='';
        }
        return $atom;
    }

    public function XDRInterpretedFunction()
    {
        $firstword = $this->todec();
        if ($firstword & Kind::_FirstWordFlag['HasAtom']) {
            //XDRAtom
            $lengthAndEncoding = $this->todec();
            $hasLatin1Chars = $lengthAndEncoding & 1;
            $length = $lengthAndEncoding >> 1;
            $atom = $this->getChars($hasLatin1Chars, $length);
            echo $atom, $this->CRLF;
        }
        $flagsword = $this->todec();
        if ($firstword & Kind::_FirstWordFlag['IsLazy']) {
            $this->XDRLazyScript();
        } else {
            //XDRScript
            $this->XDRScript();
        }
    }

    public function xdrCK_JSFunction()
    {
        $funEnclosingScopeIndex = $this->todec();
        echo 'funEnclosingScopeIndex:', $funEnclosingScopeIndex, $this->CRLF;
        $this->XDRInterpretedFunction();
    }

    public function xdrCK_JSObject()
    {
    }
}
