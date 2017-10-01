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

    protected $buildId = '';
    protected $scripts = [];

    public $bytecodes = [];
    public $bytecodeLength = 0;

    public function __construct($filename)
    {
        $this->fp = fopen($filename, 'rb');
        $this->CRLF = php_sapi_name() == 'cli' ? "\n" : "<hr>";
        $this->opcodes = unserialize(file_get_contents("Opcode.php", "r"));
        $this->init();
    }

    public function __destruct()
    {
        fclose($this->fp);
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

    public function parserVersion()
    {
        $this->parseIndex = 0;
        $buildIdLength = $this->todec();
        $buildId = '';
        $end = $buildIdLength + $this->parseIndex;
        for ($i = $this->parseIndex; $i < $end; $i++) {
            $buildId .= chr($this->bytecodes[$i]);
        }
        $this->buildId = $buildId;
        $this->parseIndex += $buildIdLength;
        return $buildId;
    }

    protected function getScriptName()
    {
        $this->parseIndex += 5;//todo
        $result = '';
        while ($this->bytecodes[$this->parseIndex]) {
            $result .= chr($this->bytecodes[$this->parseIndex]);
            $this->parseIndex++;
        }
        $this->parseIndex++;
        return $result;
    }

    public function parserHeader(Script $script)
    {
        $script->addSummary('length', $this->todec());
        $script->addSummary('mainOffset', $this->todec());
        $script->addSummary('getVersion', $this->todec());
        $script->addSummary('natoms', $this->todec());
        $script->addSummary('numNotes', $this->todec());
        $script->addSummary('consts_len', $this->todec());
        $script->addSummary('objects_len', $this->todec());
        $script->addSummary('scopes_len', $this->todec());
        $script->addSummary('trynotes_len', $this->todec());
        $script->addSummary('scopeNotes_len', $this->todec());
        $script->addSummary('yieldOffsets_len', $this->todec());
        $script->addSummary('nTypeSets', $this->todec());
        $script->addSummary('funLength', $this->todec());
        $scriptBit = $this->todec();
        $script->addSummary('scriptBits', $scriptBit);
        $scriptBits = array_flip(Kind::_ScriptBits);
        if ($scriptBit & (1 << $scriptBits['OwnSource'])) {
            $script->addSummary('buildPath', $this->getScriptName());
        }
        $script->addSummary('sourceStart_', $this->todec());
        $script->addSummary('sourceEnd_', $this->todec());
        $script->addSummary('lineno', $this->todec());
        $script->addSummary('column', $this->todec());
        $script->addSummary('nfixed', $this->todec());
        $script->addSummary('nslots', $this->todec());
        $script->addSummary('bodyScopeIndex', $this->todec());
    }

    public function parserScript(Script $script)
    {
        $opEnd = $this->parseIndex + $script->getSummary('length');
        for ($i = $this->parseIndex; $i < $opEnd;) {
            $op = $this->opcodes[$this->bytecodes[$i]];
            $i++;
            $end = $i + $op['len'] - 1;
            $bytes = [];
            for ($j = $i; $j < $end; $j++) {
                $bytes[] = $this->bytecodes[$i];
                $i++;
            }
            $script->addOperation($op['val'], $bytes);
        }
        $this->parseIndex = $opEnd;
    }

    public function parserSrcNodes(Script $script)
    {
        $end = $this->parseIndex + $script->getSummary('numNotes');
        for (; $this->parseIndex < $end; $this->parseIndex++) {
            $script->addNode($this->bytecodes[$this->parseIndex]);
        }
    }

    public function parserAtoms(Script $script)
    {
        if ($natoms = $script->getSummary('natoms')) {
            for ($i = 0; $i < $natoms; $i++) {
                $script->addAtom($this->XDRAtom());
            }
        }
    }

    public function parserScope(Script $script)
    {
        $scopeKind = Kind::_Scope[$this->todec()];
        $enclosingScopeIndex = $this->todec();
        $xdrScope = $this->getScopeXdrFunctionName($scopeKind);
        $extra = $this->$xdrScope();
        $script->addScope($scopeKind, $enclosingScopeIndex, $extra);
    }

    public function parserScopes(Script $script)
    {
        if ($scopes_len = $script->getSummary('scopes_len')) {
            for ($i = 0; $i < $scopes_len; $i++) {
                $this->parserScope($script);
            }
        }
    }

    public function parserObject(Script $script)
    {
        $classKind = Kind::_Class[$this->todec()];
        $xdrClass = $this->getObjectXdrFunctionName($classKind);
        $script->addObject($classKind, $this->$xdrClass());
    }

    public function parserObjects(Script $script)
    {
        if ($nobjects = $script->getSummary('objects_len')) {
            for ($i = 0; $i < $nobjects; $i++) {
                $this->parserObject($script);
            }
        }
    }

    public function parserTryNotes(Script $script)
    {
        if ($ntrynotes = $script->getSummary('trynotes_len')) {
            for ($i = 0; $i < $ntrynotes; $i++) {
                $script->addTryNote(
                    $kind = $this->todec(1),
                    $stackDepth = $this->todec(),
                    $start = $this->todec(),
                    $length = $this->todec()
                );
            }
        }
    }

    public function parserScopeNotes(Script $script)
    {
        if ($nscopeNotes = $script->getSummary('scopeNotes_len')) {
            for ($i = 0; $i < $nscopeNotes; $i++) {
                $script->addScopeNote(
                    $index = $this->todec(),
                    $start = $this->todec(),
                    $length = $this->todec(),
                    $parent = $this->todec()
                );
            }
        }
    }

    public function parserYieldOffsets(Script $script)
    {
        if ($nyieldOffsets = $script->getSummary('yieldOffsets_len')) {
            for ($i = 0; $i < $nyieldOffsets; $i++) {
                $script->addYieldOffset($this->todec());
            }
        }
    }

    public function parserHasLazyScript(Script $script)
    {
        $scriptBits = array_flip(Kind::_ScriptBits);
        $HasLazyScript = $scriptBits['HasLazyScript'];
        if ($script->getSummary('scriptBits') & (1 << $HasLazyScript)) {
            $script->addHasLazyScript($this->todec(8));
        }
    }

    public function printOpcodes()
    {
        /** @var Script $script * */
        foreach ($this->scripts as $index => $script) {
            $opcodes = $script->getOperations();
            echo '------------------' . $index . '------------------', $this->CRLF;
            foreach ($opcodes as $opcode) {
                $op = $this->opcodes[$opcode['id']];
                echo $op['name'], ' ', $op['len'], ' ', $op['use'], ' ', $op['def'], ' :';
                echo implode(', ', $opcode['params']), $this->CRLF;
            }
            echo '----------------------------------------', $this->CRLF;
        }
    }

    public function run()
    {
        $this->parserVersion();
        $this->XDRScript();
        echo '----------------ByteCode---------------', $this->CRLF;
        echo 'file size :', $this->bytecodeLength, $this->CRLF;
        echo 'parse size :', $this->parseIndex, $this->CRLF;
        echo '---------------------------------------', $this->CRLF;
        $this->printOpcodes();
    }

    public function XDRScript()
    {
        $script = new Script();
        $this->scripts[] = $script;
        $this->parserHeader($script);
        $this->parserScript($script);
        $this->parserSrcNodes($script);
        $this->parserAtoms($script);
        $this->parserScopes($script);
        $this->parserObjects($script);
        $this->parserTryNotes($script);
        $this->parserScopeNotes($script);
        $this->parserYieldOffsets($script);
        $this->parserHasLazyScript($script);
        return $script;
    }
}
