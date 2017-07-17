<?php

declare(strict_types = 1);

namespace MCordingley\LinearAlgebra;

final class Vector extends Matrix
{
    public function __construct(array $literal)
    {
        parent::__construct($literal);
    }

    /**************************************************************************
     * BASIC VECTOR GETTERS
     *  - getVector
     *  - getSize
     **************************************************************************/

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->internal[0];
    }

    /**
     * Get item count
     * @return int number of items
     */
    public function getSize(): int
    {
        return count($this->internal[0]);
    }

    /**************************************************************************
     * VECTOR OPERATIONS - Return a number
     *  - sum
     *  - length (magnitude)
     *  - dotProduct (innerProduct)
     **************************************************************************/

    /**
     * Sum of all elements
     *
     * @return number
     */
    public function sum()
    {
        return array_sum($this->toArray());
    }

    /**
     * Vector length (magnitude)
     * Same as l2-norm
     *
     * @return number
     */
    public function length()
    {
        return $this->l2norm();
    }

    /**
     *
     * Dot product (inner product) (A⋅B)
     * https://en.wikipedia.org/wiki/Dot_product
     *
     * @param self $other
     * @return number
     * @throws VectorException
     */
    public function dotProduct(self $other)
    {
        if ($other->getSize() !== $this->getSize()) {
            throw new VectorException('Vectors have to have same size');
        }

        return array_sum(array_map(
            function ($a, $b) {
                return $a * $b;
            },
            $this->toArray(),
            $other->toArray()
        ));
    }

    /**
     * Inner product (convience method for dot product) (A⋅B)
     *
     * @param Vector $other
     *
     * @return number
     */
    public function innerProduct(Vector $other)
    {
        return $this->dotProduct($other);
    }

    /**************************************************************************
     * VECTOR OPERATIONS - Return a Vector or Matrix
     *  - outerProduct
     *  - crossProduct
     *  - normalize
     *  - projection
     **************************************************************************/

    /**
     * Outer product (A⨂B)
     * https://en.wikipedia.org/wiki/Outer_product
     * Same as direct product.
     *
     *
     *
     *          | a₀ |                   | a₀b₀    a₀b₁    a₀b₂ |
     * A ⨂ B = | a₁ | ⨂ |b₀ b₁b₂|  =  | a₁b₀   a₁b₁  a₁b₂|
     *          | a₂ |                  | a₂b₀   a₂b₁  a₂b₂|
     *
     *
     * @param Vector $other
     *
     * @return Matrix
     */
    public function outerProduct(Vector $other): Matrix
    {
        $literal = array();
        for ($i = 0; $i < $this->getSize(); $i++) {
            for ($j = 0; $j < $other->getSize(); $j++) {
                $literal[$i][$j] = $this->toArray()[$i] * $other->toArray()[$j];
            }
        }

        return new Matrix($literal);
    }

    /**
     *
     * Cross product (AxB)
     * https://en.wikipedia.org/wiki/Cross_product
     *
     *
     * A X B = (a₁b₂ - b₁a₂) - (a₀b₂ - b₀a₂) + (a₀b₁ - b₀a₁)
     *
     * @param Vector $other
     * @return self
     * @throws VectorException
     */
    public function crossProduct(Vector $other): self
    {
        if ($other->getSize() !== 3 || $this->getSize() !== 3) {
            throw new VectorException('Vectors have to have 3 size');
        }

        $x =   ($this->toArray()[1] * $other->toArray()[2]) - ($this->toArray()[2] * $other->toArray()[1]);
        $y = -(($this->toArray()[0] * $other->toArray()[2]) - ($this->toArray()[2] * $other->toArray()[0]));
        $z =   ($this->toArray()[0] * $other->toArray()[1]) - ($this->toArray()[1] * $other->toArray()[0]);

        return new Vector([[$x, $y, $z]]);
    }

    /**
     * Normalize (Â)
     * The normalized vector Â is a vector in the same direction of A
     * but with a norm (length) of 1. It is a unit vector.
     * http://mathworld.wolfram.com/NormalizedVector.html
     *
     *      A
     * Â ≡ ---
     *     |A|
     *
     *  where |A| is the l²-norm (|A|₂)
     *
     */
    public function normalize(): self
    {
        $norm = $this->l2norm();

        return $this->divideScalar($norm);
    }

    /**
     * Projection of A onto B
     * https://en.wikipedia.org/wiki/Vector_projection#Vector_projection
     *
     *          A⋅B
     * projᵇA = --- B
     *          |B|²
     *
     * @param self $other
     *
     * @return self
     */
    public function projection(self $other): self
    {
        return $other->multiplyScalar($this->dotProduct($other) / (($other->l2norm())**2));
    }

    /**************************************************************************
     * VECTOR NORMS
     *  - l1Norm
     *  - l2Norm
     *  - maxNorm
     **************************************************************************/

    /**
     * l₁-norm (|x|₁)
     * Also known as Taxicab norm or Manhattan norm
     *
     * https://en.wikipedia.org/wiki/Norm_(mathematics)#Taxicab_norm_or_Manhattan_norm
     *
     * |x|₁ = ∑|xᵢ|
     *
     * @return number
     */
    public function l1Norm()
    {
        $sum = 0;

        foreach ($this->toArray() as $value) {
            $sum += abs($value);
        }

        return $sum;
    }

    /**
     * l²-norm (|x|₂)
     * Also known as Euclidean norm, Euclidean length, L² distance, ℓ² distance
     * Used to normalize a vector.
     *
     * http://mathworld.wolfram.com/L2-Norm.html
     * https://en.wikipedia.org/wiki/Norm_(mathematics)#Euclidean_norm
     *         ______
     * |x|₂ = √∑|xᵢ|²
     *
     * @return float
     */
    public function l2Norm(): float
    {
        $literal = [];

        for ($i = 0, $rows = $this->getSize(); $i < $rows; $i++) {
            $literal[] = $this->toArray()[$i] ** 2;
        }

        return sqrt(array_sum($literal));
    }

    /**
     * Max norm (infinity norm) (|x|∞)
     *
     * |x|∞ = max |x|
     *
     * @return number
     */
    public function maxNorm()
    {
        $max = abs($this->toArray()[0]);

        foreach($this->toArray() as $value) {
            if(abs($value) > $max) {
                $max = abs($value);
            }
        }

        return $max;
    }
}
