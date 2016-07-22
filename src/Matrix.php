<?php

namespace mcordingley\LinearAlgebra;

use ArrayAccess;
use Exception;

class Matrix implements ArrayAccess
{
    /**
     * Number of columns in the matrix.
     *
     * @var int
     */
    protected $columnCount;

    /**
     * Number of rows in the matrix.
     *
     * @var int
     */
    protected $rowCount;

    /**
     * Internal array for the matrix data that this class wraps.
     *
     * @var array
     */
    protected $internal;

    /**
     * LU Decomposition of this matrix, lazily created as needed.
     *
     * @var LUDecomposition
     */
    protected $LU = null;

    /**
     * __construct
     *
     * Example:
     *      $transform = new Matrix([
     *          [0, 1, 2],
     *          [3, 4, 5],
     *          [6, 7, 8]
     *      ]);
     *
     * @param array $literal Array representation of the matrix.
     * @throws MatrixException
     */
    public function __construct(array $literal)
    {
        if (!$this->isLiteralValid($literal)) {
            throw new MatrixException('Invalid array provided: ' . print_r($literal, true));
        }

        $this->internal = $literal;

        $this->rowCount = count($literal);
        $this->columnCount = count($literal[0]);
    }

    /**
     * Magic method to make the public "properties" read-only.
     *
     * @param string $property
     * @return int|null
     */
    public function __get($property)
    {
        switch ($property) {
            case 'columns':
                return $this->columnCount;
            case 'rows':
                return $this->rowCount;
            default:
                return null;
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $rowStrings = array_map(function ($row) {
            return '[' . implode(', ', $row) . ']';
        }, $this->internal);

        return '[' . implode(', ', $rowStrings) . ']';
    }

    /**
     * Adds either another matrix or a scalar to the current matrix, returning
     * a new matrix instance.
     *
     * @param Matrix|int|float $value Matrix or scalar to add to this matrix
     * @return Matrix
     * @throws MatrixException
     */
    public function add($value)
    {
        if ($value instanceof Matrix) {
            if ($this->rows != $value->rows || $this->columns != $value->columns) {
                throw new MatrixException('Cannot add two matrices of different size.');
            }

            return $this->map(function ($element, $i, $j) use ($value) {
                return $element + $value->get($i, $j);
            });
        }

        return $this->map(function ($element) use ($value) {
            return $element + $value;
        });
    }

    /**
     * Creates and returns a new matrix that is the adjoint of this matrix.
     *
     * @return Matrix
     * @throws MatrixException
     */
    public function adjoint()
    {
        if (!$this->isSquare()) {
            throw new MatrixException('Adjoints can only be called on square matrices: ' . print_r($this->internal, true));
        }

        return $this->inverse()->multiply($this->determinant());
    }

    /**
     * @return float The matrix's determinant
     * @throws MatrixException
     */
    public function determinant()
    {
        if (!$this->isSquare()) {
            throw new MatrixException('Determinants can only be called on square matrices: ' . print_r($this->internal, true));
        }

        // Base case for a 1 by 1 matrix
        if ($this->rows == 1) {
            return $this->get(0, 0);
        }

        return $this->getLUDecomp()->determinant();
    }

    /**
     * @return Matrix
     */
    public function diagonal()
    {
        $diagonal = [];
        $max = min([$this->rows, $this->columns]);

        for ($i = 0; $i < $max; $i++) {
            $diagonal[] = $this->get($i, $i);
        }

        return new static([$diagonal]);
    }

    /**
     * @param Matrix $matrixB
     * @return boolean True if equal. False otherwise.
     */
    public function equals(Matrix $matrixB)
    {
        if ($this->rowCount != $matrixB->rowCount || $this->columnCount != $matrixB->columnCount) {
            return false;
        }

        for ($i = $this->rowCount; $i--;) {
            for ($j = $this->columnCount; $j--;) {
                if ($this->get($i, $j) != $matrixB->get($i, $j)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param int $row Which zero-based row index to access.
     * @param int $column Which zero-based column index to access.
     * @return float
     */
    public function get($row, $column)
    {
        return $this->internal[$row][$column];
    }

    /**
     * @param int $size How many rows and columns the identity matrix should have
     * @return Matrix
     */
    public static function identity($size)
    {
        $literal = [];

        for ($i = 0; $i < $size; ++$i) {
            $literal[] = [];

            for ($j = 0; $j < $size; ++$j) {
                $literal[$i][] = ($i == $j) ? 1 : 0;
            }
        }

        return new static($literal);
    }

    /**
     * @return Matrix
     * @throws MatrixException
     */
    public function inverse()
    {
        if (!$this->isSquare()) {
            throw new MatrixException('Inverse can only be called on square matrices: ' . print_r($this->internal, true));
        }

        if ($this->determinant() == 0) {
            throw new MatrixException('This matrix has a zero determinant and is therefore not invertable: ' . print_r($this->internal, true));
        }

        if ($this->isSymmetric()) {
            try {
                return $this->choleskyInverse();
            } catch (Exception $exception) {
                // Allow this to fall through to the more general algorithm.
            }
        }

        // Use LU decomposition for the general case.
        return $this->getLUDecomp()->inverse();
    }

    /**
     * @return boolean True if the matrix is square, false otherwise.
     */
    public function isSquare()
    {
        return $this->rows == $this->columns;
    }

    /**
     * @return boolean
     */
    public function isSymmetric()
    {
        if (!$this->isSquare()) {
            return false;
        }

        for ($i = 0; $i < $this->rows; ++$i) {
            for ($j = 0; $j < $this->columns; ++$j) {
                if ($i == $j) {
                    continue;
                }

                if ($this->get($i, $j) != $this->get($j, $i)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Iterates over the current matrix with a callback function to return a new
     * matrix with the mapped values. $callback takes four arguments:
     * - The current matrix element
     * - The current row
     * - The current column
     * - The matrix being iterated over
     *
     * @param callable $callback A function that returns the computed new values.
     * @return Matrix
     */
    public function map(callable $callback)
    {
        $literal = [];

        for ($i = 0; $i < $this->rows; $i++) {
            $row = [];

            for ($j = 0; $j < $this->columns; $j++) {
                $row[] = $callback($this->get($i, $j), $i, $j, $this);
            }

            $literal[] = $row;
        }

        return new static($literal);
    }

    /**
     * Multiplies either another matrix or a scalar with the current matrix,
     * returning a new matrix instance.
     *
     * @param Matrix|int|float $value Matrix or scalar to multiply with this matrix
     * @return Matrix
     * @throws MatrixException
     */
    public function multiply($value)
    {
        if ($value instanceof Matrix) {
            if ($this->columns != $value->rows) {
                throw new MatrixException('Cannot multiply matrices of these sizes.');
            }

            $literal = [];

            for ($i = 0; $i < $this->rows; $i++) {
                $row = [];

                for ($j = 0; $j < $value->columns; $j++) {
                    $sum = 0;

                    for ($k = 0; $k < $this->columns; $k++) {
                        $sum += $this->get($i, $k) * $value->get($k, $j);
                    }

                    $row[] = $sum;
                }

                $literal[] = $row;
            }

            return new static($literal);
        } else {
            return $this->map(function ($element) use ($value) {
                return $element * $value;
            });
        }
    }

    /**
     * Returns a new matrix with the selected row and column removed, useful for
     * calculating determinants or other recursive operations on matrices.
     *
     * @param int $row Row to remove, null to remove no row.
     * @param int $column Column to remove, null to remove no column.
     * @return Matrix
     */
    public function submatrix($row = null, $column = null)
    {
        $literal = [];

        for ($i = 0; $i < $this->rows; $i++) {
            if ($i === $row) {
                continue;
            }

            $rowLiteral = [];

            for ($j = 0; $j < $this->columns; $j++) {
                if ($j === $column) {
                    continue;
                }

                $rowLiteral[] = $this->get($i, $j);
            }

            $literal[] = $rowLiteral;
        }

        return new static($literal);
    }

    /**
     * Subtracts either another matrix or a scalar from the current matrix,
     * returning a new matrix instance.
     *
     * @param Matrix|int|float $value Matrix or scalar to subtract from this matrix
     * @return Matrix
     * @throws MatrixException
     */
    public function subtract($value)
    {
        if ($value instanceof Matrix) {
            if ($this->rows != $value->rows || $this->columns != $value->columns) {
                throw new MatrixException('Cannot subtract two matrices of different size.');
            }

            return $this->map(function ($element, $i, $j) use ($value) {
                return $element - $value->get($i, $j);
            });
        } else {
            return $this->map(function ($element) use ($value) {
                return $element - $value;
            });
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->internal;
    }

    /**
     * Sums the main diagonal values of a square matrix.
     * @return float
     * @throws MatrixException
     */
    public function trace()
    {
        if (!$this->isSquare()) {
            throw new MatrixException('Trace can only be called on square matrices: ' . print_r($this->internal, true));
        }

        $trace = 0;

        for ($i = 0; $i < $this->rows; $i++) {
            $trace += $this->get($i, $i);
        }

        return $trace;
    }

    /**
     * @return Matrix
     */
    public function transpose()
    {
        $literal = [];

        for ($i = 0; $i < $this->columns; $i++) {
            $literal[] = [];

            for ($j = 0; $j < $this->rows; $j++) {
                $literal[$i][] = $this->get($j, $i);
            }
        }

        return new static($literal);
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->internal[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->internal[$offset];
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws MatrixException
     */
    public function offsetSet($offset, $value)
    {
        throw new MatrixException('Attempt to set a value on a matrix. Matrix instances are immutable.');
    }

    /**
     * @param mixed $offset
     * @throws MatrixException
     */
    public function offsetUnset($offset)
    {
        throw new MatrixException('Attempt to unset a value on a matrix. Matrix instances are immutable.');
    }

    /**
     * Returns the Cholesky decomposition of a matrix.
     * Matrix must be square and symmetrical for this to work.
     * Returns just the lower triangular matrix, as the upper is a mirror image
     * of that.
     *
     * @return Matrix
     * @throws MatrixException
     */
    protected function choleskyDecomposition()
    {
        $literal = $this->toArray();
        $rows = count($literal);

        $ztol = 1.0e-5;

        // Zero-fill an array-representation of a matrix
        $t = [];

        for ($i = 0; $i < $rows; ++$i) {
            $t[] = [];

            for ($j = 0; $j < $rows; ++$j) {
                $t[$i][] = 0;
            }
        }

        for ($i = 0; $i < $rows; ++$i) {
            $S = 0;

            for ($k = 0; $k < $i; ++$k) {
                $S += pow($t[$k][$i], 2);
            }

            $d = $this->get($i, $i) - $S;

            if (abs($d) < $ztol) {
                $t[$i][$i] = 0;
            } else {
                if ($d < 0) {
                    throw new MatrixException("Matrix not positive-definite");
                }

                $t[$i][$i] = sqrt($d);
            }

            for ($j = $i + 1; $j < $rows; ++$j) {
                $S = 0;

                for ($k = 0; $k < $i; ++$i) {
                    if (!isset($t[$k]) || !isset($t[$k][$i]) || !isset($t[$k][$j])) {
                        break;
                    }
                    $S += $t[$k][$i] * $t[$k][$j];
                }

                if (abs($S) < $ztol) {
                    $S = 0;
                }

                try {
                    if (isset($t[$i]) && isset($t[$i][$i]) && isset($literal[$i]) && isset($literal[$i][$j])) {
                        $t[$i][$j] = ($literal[$i][$j] - $S) / $t[$i][$i];
                    } else {
                        $t[$i][$j] = $S;
                    }
                } catch (Exception $exception) {
                    throw new MatrixException("Zero diagonal");
                }
            }
        }

        return new Matrix($t);
    }

    /**
     * @return Matrix
     */
    protected function choleskyInverse()
    {
        //Translated from: http://adorio-research.org/wordpress/?p=4560

        $t = $this->choleskyDecomposition()->toArray();

        $B = [];

        for ($i = 0; $i < $this->rowCount; ++$i) {
            $B[] = [];

            for ($j = 0; $j < $this->rowCount; ++$j) {
                $B[$i][] = 0;
            }
        }

        for ($j = $this->rowCount; $j--;) {
            $tjj = $t[$j][$j];

            $S = 0;
            for ($k = $j + 1; $k < $this->rowCount; ++$k) {
                $S += $t[$j][$k] * $B[$j][$k];
            }

            $B[$j][$j] = 1 / pow($tjj, 2) - $S / $tjj;

            for ($i = $j; $i--;) {
                $sum = 0;

                for ($k = $i + 1; $k < $this->rowCount; ++$k) {
                    $sum += $t[$i][$k] * $B[$k][$j];
                }

                $B[$j][$i] = $B[$i][$j] = -$sum / $t[$i][$i];
            }
        }

        return new Matrix($B);
    }

    /**
     * Lazy-loads the LU decomposition. If it has already been built for this
     * matrix, it returns the existing one. Otherwise, it creates a new one.
     *
     * @return LUDecomposition
     */
    protected function getLUDecomp()
    {
        if (!$this->LU) {
            $this->LU = new LUDecomposition($this);
        }

        return $this->LU;
    }

    /**
     * Tests an array representation of a matrix to see if it would make a valid matrix
     *
     * @param array $literal
     * @return boolean
     */
    protected function isLiteralValid(array $literal)
    {
        // Matrix must have at least one row
        if (!count($literal)) {
            return false;
        }

        // Matrix must have at least one column
        if (!count($literal[0])) {
            return false;
        }

        // Matrix must have the same number of columns in each row
        $lastRow = false;

        foreach ($literal as $row) {
            $thisRow = count($row);

            if ($lastRow !== false && $lastRow != $thisRow) {
                return false;
            }

            $lastRow = $thisRow;
        }

        return true;
    }
}
