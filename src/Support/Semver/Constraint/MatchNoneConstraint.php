<?php

namespace LaraGram\Brain\Support\Semver\Constraint;

class MatchNoneConstraint implements ConstraintInterface
{
    /** @var string|null */
    protected $prettyString;

    /**
     * @param ConstraintInterface $provider
     *
     * @return bool
     */
    public function matches(ConstraintInterface $provider)
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function compile($otherOperator)
    {
        return 'false';
    }

    /**
     * {@inheritDoc}
     */
    public function setPrettyString($prettyString)
    {
        $this->prettyString = $prettyString;
    }

    /**
     * {@inheritDoc}
     */
    public function getPrettyString()
    {
        if ($this->prettyString) {
            return $this->prettyString;
        }

        return (string) $this;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return '[]';
    }

    /**
     * {@inheritDoc}
     */
    public function getUpperBound()
    {
        return new Bound('0.0.0.0-dev', false);
    }

    /**
     * {@inheritDoc}
     */
    public function getLowerBound()
    {
        return new Bound('0.0.0.0-dev', false);
    }
}
