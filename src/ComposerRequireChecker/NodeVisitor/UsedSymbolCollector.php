<?php

namespace ComposerRequireChecker\NodeVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;

final class UsedSymbolCollector extends NodeVisitorAbstract
{
    /**
     * @var NameResolver
     */
    private $nameResolver;

    /**
     * @var mixed[]
     */
    private $collectedSymbols = [];

    public function __construct()
    {
        $this->nameResolver     = new NameResolver();
        $this->collectedSymbols = [];
    }

    /**
     * {@inheritDoc}
     */
    public function beforeTraverse(array $nodes)
    {
        return $this->nameResolver->beforeTraverse($nodes);
    }

    /**
     * {@inheritDoc}
     */
    public function enterNode(Node $node)
    {
        $replacementNode = $this->nameResolver->enterNode($node);

        $this->recordExtendsUsage($node);
        $this->recordImplementsUsage($node);
        $this->recordClassExpressionUsage($node);
        $this->recordCatchUsage($node);
        $this->recordFunctionCallUsage($node);
        $this->recordConstantFetchUsage($node);
        $this->recordTraitUsage($node);

        return $replacementNode;
    }

    private function recordExtendsUsage(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            array_map([$this, 'recordUsageOf'], array_filter([$node->extends]));
        }
    }

    private function recordImplementsUsage(Node $node)
    {
        if ($node instanceof Node\Stmt\Class_) {
            array_map([$this, 'recordUsageOf'], $node->implements);
        }
    }

    private function recordClassExpressionUsage(Node $node)
    {
        if (
            (
                $node instanceof Node\Expr\StaticCall
                || $node instanceof Node\Expr\StaticPropertyFetch
                || $node instanceof Node\Expr\ClassConstFetch
                || $node instanceof Node\Expr\New_
                || $node instanceof Node\Expr\Instanceof_
            )
            && ($nodeClass = $node->class)
            && $nodeClass instanceof Node\Name
        ) {
            $this->recordUsageOf($nodeClass);
        }
    }

    private function recordCatchUsage(Node $node)
    {
        if ($node instanceof Node\Stmt\Catch_) {
            $this->recordUsageOf($node->type);
        }
    }

    private function recordFunctionCallUsage(Node $node)
    {
        if (
            $node instanceof Node\Expr\FuncCall
            && ($nodeName = $node->name)
            && $nodeName instanceof Node\Name
        ) {
            $this->recordUsageOf($nodeName);
        }
    }

    private function recordConstantFetchUsage(Node $node)
    {
        if ($node instanceof Node\Expr\ConstFetch) {
            $this->recordUsageOf($node->name);
        }
    }

    private function recordTraitUsage(Node $node)
    {
        if (! $node instanceof Node\Stmt\TraitUse) {
            return;
        }

        array_map([$this, 'recordUsageOf'], $node->traits);

        foreach ($node->adaptations as $adaptation) {
            $this->recordUsageOf($adaptation);

            if ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Precedence) {
                array_map([$this, 'recordUsageOf'], $adaptation->insteadof);
            }
        }
    }

    /**
     * @param Node\Name $symbol
     *
     * @return void
     */
    private function recordUsageOf(Node\Name $symbol)
    {
        $this->collectedSymbols[(string) $symbol] = $symbol;
    }
}