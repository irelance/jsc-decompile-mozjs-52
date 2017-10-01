<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/9/20
 * Time: 上午8:04
 *
 * js/src/jsscript.h
 * js/src/vm/Xdr.h
 */
class Decompile
{
    use Xdr;
    private $fp;
    private $CRLF;
    protected $opcodes = [];
    protected $parseIndex = 0;

    protected $head = [];

    public $bytecodes = [];
    public $bytecodeLength = 0;

    public function __construct($filename)
    {
        $this->fp = fopen($filename, 'rb');
        $this->CRLF = php_sapi_name() == 'cli' ? "\n" : "<hr>";
        $this->opcodes = unserialize(file_get_contents("Opcode.php", "r"));
        $this->init();
    }

    public function init()
    {
        $i = 0;
        while (!feof($this->fp)) {
            $c = fgetc($this->fp);
            $this->bytecodes[$i] = ord($c);
            $i++;
        }
        $this->bytecodeLength = count($this->bytecodes);
    }

    protected function todec($length = 4)//length include start
    {
        $result = '';
        for ($i = $this->parseIndex + $length - 1; $i >= $this->parseIndex; $i--) {
            $result .= sprintf('%02s', dechex($this->bytecodes[$i]));
        }
        $this->parseIndex += $length;
        return hexdec($result);
    }

    protected function getBuildId()
    {
        $this->parseIndex = 0;
        $buildIdLength = $this->todec();
        $buildId = '';
        $end = $buildIdLength + $this->parseIndex;
        for ($i = $this->parseIndex; $i < $end; $i++) {
            $buildId .= chr($this->bytecodes[$i]);
        }
        $this->head['buildid'] = $buildId;
        echo 'buildid :', $buildId, $this->CRLF;
        $this->parseIndex += $buildIdLength;
        return $buildId;
    }

    protected function simpleInfo($columnName)
    {
        $this->head[$columnName] = $this->todec();
        echo $columnName, ' :', $this->head[$columnName], $this->CRLF;
        return $this->head[$columnName];
    }

    protected function getScriptName()
    {
        $this->parseIndex += 5;
        $result = '';
        while ($this->bytecodes[$this->parseIndex]) {
            $result .= chr($this->bytecodes[$this->parseIndex]);
            $this->parseIndex++;
        }
        $this->head['build_path'] = $result;
        echo 'build_path :', $this->head['build_path'], $this->CRLF;
        $this->parseIndex++;
        return $result;
    }

    public function parserVersion()
    {
        echo '----------------Version----------------', $this->CRLF;
        $this->getBuildId();
        echo '---------------------------------------', $this->CRLF;
    }

    public function parserHeader()
    {
        echo '----------------HEAD----------------', $this->CRLF;
        $this->simpleInfo('length');
        $this->simpleInfo('mainOffset');
        $this->simpleInfo('getVersion');
        $this->simpleInfo('natoms');
        $this->simpleInfo('numNotes');
        $this->simpleInfo('consts_len');
        $this->simpleInfo('objects_len');
        $this->simpleInfo('scopes_len');
        $this->simpleInfo('trynotes_len');
        $this->simpleInfo('scopeNotes_len');
        $this->simpleInfo('yieldOffsets_len');
        $this->simpleInfo('nTypeSets');
        $this->simpleInfo('funLength');
        $this->simpleInfo('scriptBits');
        $scriptBits = array_flip(Kind::_ScriptBits);
        if ($this->head['scriptBits'] & (1 << $scriptBits['OwnSource'])) {
            $this->getScriptName();
        }
        $this->simpleInfo('sourceStart_');
        $this->simpleInfo('sourceEnd_');
        $this->simpleInfo('lineno');
        $this->simpleInfo('column');
        $this->simpleInfo('nfixed');
        $this->simpleInfo('nslots');
        $this->simpleInfo('bodyScopeIndex');
        echo '---------------------------------------', $this->CRLF;
    }

    protected function parserOpcodes($opEnd)
    {
        for ($i = $this->parseIndex; $i < $opEnd;) {
            $op = $this->opcodes[$this->bytecodes[$i]];
            $i++;
            echo $op['name'], ' ', $op['len'], ' ', $op['use'], ' ', $op['def'], ' :';
            $end = $i + $op['len'] - 1;
            for ($j = $i; $j < $end; $j++) {
                echo ' ', $this->bytecodes[$i];
                $i++;
            }
            echo $this->CRLF;
        }
        $this->parseIndex = $opEnd;
    }

    public function parserScript()
    {
        echo '----------------OpCode----------------', $this->CRLF;
        $opEnd = $this->parseIndex + $this->head['length'];
        $this->parserOpcodes($opEnd);
        echo '---------------------------------------', $this->CRLF;
    }

    public function parserSrcNodes()
    {
        echo '-----------------Nodes-----------------', $this->CRLF;
        $end = $this->parseIndex + $this->head['numNotes'];
        for (; $this->parseIndex < $end; $this->parseIndex++) {
            echo $this->bytecodes[$this->parseIndex], ' , ';
        }
        echo "\n";
        echo '---------------------------------------', $this->CRLF;
    }

    public function parserAtom()
    {
        $lengthAndEncoding = $this->todec();
        //echo $lengthAndEncoding, $this->CRLF;
        $length = $lengthAndEncoding >> 1;
        //echo $length, $this->CRLF;
        $encoding = $lengthAndEncoding & 1;
        //echo $encoding, $this->CRLF;
        $atom = $this->getChars($encoding, $length);
        echo $atom, $this->CRLF;
    }

    public function parserAtoms()
    {
        if ($this->head['natoms']) {
            echo '----------------Atoms----------------', $this->CRLF;
            for ($i = 0; $i < $this->head['natoms']; $i++) {
                $this->parserAtom();
            }
            echo '---------------------------------------', $this->CRLF;
        }
    }

    public function parserScope()
    {
        $scopeKind = Kind::_Scope[$this->todec()];
        $enclosingScopeIndex = $this->todec();
        echo $scopeKind, ':', $enclosingScopeIndex, $this->CRLF;
        $xdrScope = $this->getScopeXdrFunctionName($scopeKind);
        $this->$xdrScope();
    }

    public function parserScopes()
    {
        if ($this->head['scopes_len']) {
            echo '----------------Scopes----------------', $this->CRLF;
            for ($i = 0; $i < $this->head['scopes_len']; $i++) {
                $this->parserScope();
            }
            echo '---------------------------------------', $this->CRLF;
        }
    }

    public function parserObject()
    {
        $classKind = Kind::_Class[$this->todec()];
        echo 'classKind:', $classKind, $this->CRLF;
        $xdrClass = $this->getObjectXdrFunctionName($classKind);
        $this->$xdrClass();
    }

    public function parserObjects()
    {
        if ($this->head['objects_len']) {
            echo '----------------Objects----------------', $this->CRLF;
            for ($i = 0; $i < $this->head['objects_len']; $i++) {
                $this->parserObject();
            }
            echo '---------------------------------------', $this->CRLF;
        }
    }

    public function parserTryNote()
    {
        $kind = $this->todec(1);
        $stackDepth = $this->todec();
        $start = $this->todec();
        $length = $this->todec();
        echo $kind, ' :', $stackDepth, ' , ', $start, ' - ', $length, $this->CRLF;
    }

    public function parserTryNotes()
    {
        if ($this->head['trynotes_len']) {
            echo '---------------TryNotes----------------', $this->CRLF;
            for ($i = 0; $i < $this->head['trynotes_len']; $i++) {
                $this->parserTryNote();
            }
            echo '---------------------------------------', $this->CRLF;
        }
    }

    public function parserScopeNote()
    {
        $index = $this->todec();
        $start = $this->todec();
        $length = $this->todec();
        $parent = $this->todec();
        echo $index, '->', $parent, ' :', $start, ' - ', $length, $this->CRLF;
    }

    public function parserScopeNotes()
    {
        if ($this->head['scopeNotes_len']) {
            echo '--------------ScopeNotes---------------', $this->CRLF;
            for ($i = 0; $i < $this->head['scopeNotes_len']; $i++) {
                $this->parserScopeNote();
            }
            echo '---------------------------------------', $this->CRLF;
        }
    }

    public function parserYieldOffsets()
    {
        if ($this->head['yieldOffsets_len']) {
            echo '-------------YieldOffsets-------------', $this->CRLF;
            for ($i = 0; $i < $this->head['yieldOffsets_len']; $i++) {
                $offset = $this->todec();
                echo 'offset :', $offset, $this->CRLF;
            }
            echo '---------------------------------------', $this->CRLF;
        }
    }

    public function parserHasLazyScript()
    {
        $scriptBits = array_flip(Kind::_ScriptBits);
        $HasLazyScript = $scriptBits['HasLazyScript'];
        if ($this->head['scriptBits'] & (1 << $HasLazyScript)) {
            echo '-------------HasLazyScript-------------', $this->CRLF;
            $packedFields = $this->todec(8);
            echo 'packedFields :', $packedFields, $this->CRLF;
            echo '---------------------------------------', $this->CRLF;
        }
    }

    public function run()
    {
        $this->parserVersion();
        $this->XDRScript();
    }

    public function XDRScript()
    {
        $this->parserHeader();
        $this->parserScript();
        $this->parserSrcNodes();
        $this->parserAtoms();
        $this->parserScopes();
        $this->parserObjects();
        $this->parserTryNotes();
        $this->parserScopeNotes();
        $this->parserYieldOffsets();
        $this->parserHasLazyScript();
        echo '----------------ByteCode---------------', $this->CRLF;
        echo 'file size :', $this->bytecodeLength, $this->CRLF;
        echo 'parse size :', $this->parseIndex, $this->CRLF;
        echo '---------------------------------------', $this->CRLF;
    }
}
