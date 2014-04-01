<?php

namespace MCordingley\Matrix;

class Matrix {
    protected $rowCount;
    protected $columnCount;

    // Internal array representation of the matrix
    protected $internal;
    
    /**
     * Constructor
     * 
     * Creates a new matrix. e.g. 
     *      $transform = new Matrix([
     *          [0, 1, 2],
     *          [3, 4, 5],
     *          [6, 7, 8]
     *      ]);
     * 
     * @param array $literal Array representation of the matrix.
     */
    public function __construct(array $literal) {
        if (!$this->isLiteralValid($literal)) {
            throw new MatrixException('Invalid array provided: ' . print_r($literal, true));
        }
        
        $this->internal = $literal;
        
        $this->rowCount = count($literal);
        $this->columnCount = count($literal[0]);
    }
    
    /**
     * isLiteralValid
     * 
     * Tests a literal value to see if it's valid input for a new instance of
     * this class.
     * 
     * @param array $literal Array literal representation of this class.
     * @return boolean True if a valid representation. False otherwise.
     */
    protected function isLiteralValid(array $literal) {
        // Check size
        
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
    
    /**
     * get
     * 
     * @param int $row Which zero-based row index to access.
     * @param int $column Which zero-based column index to access.
     * @return numeric The value at $row, $column position in the matrix.
     */
    public function get($row, $column) {
        return $this->internal[$row][$column];
    }
    
    /**
     * set
     * 
     * @param int $row Which zero-based row index to set.
     * @param int $column Which zero-based column index to set.
     * @param numeric $value The new value for the position at $row, $column.
     * @return \MCordingley\Matrix\Matrix
     */
    public function set($row, $column, $value) {
        $this->internal[$row][$column] = $value;
        
        return $this;
    }
    
    public function __get($property) {
        switch ($property) {
            case 'columns':
                return $this->columnCount;
            case 'rows':
                return $this->rowCount;
            default:
                return null;
        }
    }
}