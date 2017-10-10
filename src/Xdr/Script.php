<?php
/**
 * Created by PhpStorm.
 * User: irelance
 * Date: 2017/10/10
 * Time: 上午11:30
 */

namespace Irelance\Mozjs52\Xdr;

use Irelance\Mozjs52\Context;
use Irelance\Mozjs52\Constant;

/**
 * @method \Irelance\Mozjs52\Xdr\Common todec(int $length = 4)
 *
 * @property integer $parseIndex
 * @property array $bytecodes
 */
trait Script
{
    protected function getScriptName()
    {
        $this->parseIndex += 5;//todo find the reason
        $result = '';
        while ($this->bytecodes[$this->parseIndex]) {
            $result .= chr($this->bytecodes[$this->parseIndex]);
            $this->parseIndex++;
        }
        $this->parseIndex++;
        return $result;
    }

    protected function parserHeader(Context $context)
    {
        $context->addSummary('length', $this->todec());
        $context->addSummary('mainOffset', $this->todec());
        $context->addSummary('getVersion', $this->todec());
        $context->addSummary('natoms', $this->todec());
        $context->addSummary('numNotes', $this->todec());
        $context->addSummary('consts_len', $this->todec());
        $context->addSummary('objects_len', $this->todec());
        $context->addSummary('scopes_len', $this->todec());
        $context->addSummary('trynotes_len', $this->todec());
        $context->addSummary('scopeNotes_len', $this->todec());
        $context->addSummary('yieldOffsets_len', $this->todec());
        $context->addSummary('nTypeSets', $this->todec());
        $context->addSummary('funLength', $this->todec());
        $scriptBit = $this->todec();
        $context->addSummary('scriptBits', $scriptBit);
        $scriptBits = array_flip(Constant::_ScriptBits);
        if ($scriptBit & (1 << $scriptBits['OwnSource'])) {
            $context->addSummary('buildPath', $this->getScriptName());
        }
        $context->addSummary('sourceStart_', $this->todec());
        $context->addSummary('sourceEnd_', $this->todec());
        $context->addSummary('lineno', $this->todec());
        $context->addSummary('column', $this->todec());
        $context->addSummary('nfixed', $this->todec());
        $context->addSummary('nslots', $this->todec());
        $context->addSummary('bodyScopeIndex', $this->todec());
    }

    protected function parserScript(Context $context)
    {
        $opEnd = $this->parseIndex + $context->getSummary('length');
        for ($i = $this->parseIndex; $i < $opEnd;) {
            $op = Constant::_Opcode[$this->bytecodes[$i]];
            $i++;
            $end = $i + $op['len'] - 1;
            $bytes = [];
            for ($j = $i; $j < $end; $j++) {
                $bytes[] = $this->bytecodes[$i];
                $i++;
            }
            $context->addOperation($op['val'], $bytes);
        }
        $this->parseIndex = $opEnd;
    }

    protected function parserSrcNodes(Context $context)
    {
        $end = $this->parseIndex + $context->getSummary('numNotes');
        for (; $this->parseIndex < $end; $this->parseIndex++) {
            $context->addNode($this->bytecodes[$this->parseIndex]);
        }
    }

    protected function parserAtoms(Context $context)
    {
        if ($natoms = $context->getSummary('natoms')) {
            for ($i = 0; $i < $natoms; $i++) {
                $context->addAtom($this->XDRAtom());
            }
        }
    }

    protected function parseConsts(Context $context)
    {
        if ($nconsts = $context->getSummary('consts_len')) {
            for ($i = 0; $i < $nconsts; $i++) {
                $context->addConst($this->xdrConst());
            }
        }
    }

    protected function parserScope(Context $context)
    {
        $context->addScope(
            $scopeKind = Constant::_Scope[$this->todec()],
            $enclosingScopeIndex = $this->todec(),
            $extra = $this->xdrScopeExtra($scopeKind)
        );
    }

    protected function parserScopes(Context $context)
    {
        if ($scopes_len = $context->getSummary('scopes_len')) {
            for ($i = 0; $i < $scopes_len; $i++) {
                $this->parserScope($context);
            }
        }
    }

    protected function parserObject(Context $context)
    {
        $context->addObject(
            $classKind = Constant::_Class[$this->todec()],
            $extra = $this->xdrObjectExtra($classKind)
        );
    }

    protected function parserObjects(Context $context)
    {
        if ($nobjects = $context->getSummary('objects_len')) {
            for ($i = 0; $i < $nobjects; $i++) {
                $this->parserObject($context);
            }
        }
    }

    protected function parserTryNotes(Context $context)
    {
        if ($ntrynotes = $context->getSummary('trynotes_len')) {
            for ($i = 0; $i < $ntrynotes; $i++) {
                $context->addTryNote(
                    $kind = $this->todec(1),
                    $stackDepth = $this->todec(),
                    $start = $this->todec(),
                    $length = $this->todec()
                );
            }
        }
    }

    protected function parserScopeNotes(Context $context)
    {
        if ($nscopeNotes = $context->getSummary('scopeNotes_len')) {
            for ($i = 0; $i < $nscopeNotes; $i++) {
                $context->addScopeNote(
                    $index = $this->todec(),
                    $start = $this->todec(),
                    $length = $this->todec(),
                    $parent = $this->todec()
                );
            }
        }
    }

    protected function parserYieldOffsets(Context $context)
    {
        if ($nyieldOffsets = $context->getSummary('yieldOffsets_len')) {
            for ($i = 0; $i < $nyieldOffsets; $i++) {
                $context->addYieldOffset($this->todec());
            }
        }
    }

    protected function parserHasLazyScript(Context $context)
    {
        $scriptBits = array_flip(Constant::_ScriptBits);
        $HasLazyScript = $scriptBits['HasLazyScript'];
        if ($context->getSummary('scriptBits') & (1 << $HasLazyScript)) {
            $context->addHasLazyScript($this->todec(8));
        }
    }

    public function XDRScript()
    {
        $context = new Context();
        $this->contexts[] = $context;
        $this->parserHeader($context);
        $this->parserScript($context);
        $this->parserSrcNodes($context);
        $this->parserAtoms($context);
        $this->parseConsts($context);
        $this->parserScopes($context);
        $this->parserObjects($context);
        $this->parserTryNotes($context);
        $this->parserScopeNotes($context);
        $this->parserYieldOffsets($context);
        $this->parserHasLazyScript($context);
        return $context;
    }
}
