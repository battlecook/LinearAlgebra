<?php
namespace MathPHP\Tests\LinearAlgebra;

use MCordingley\LinearAlgebra\MatrixException;
use MCordingley\LinearAlgebra\Vector;

class VectorTest extends \PHPUnit_Framework_TestCase
{
    private function buildVector()
    {
        return new Vector([
            [1, 2, 3, 4],
        ]);
    }

    public function testEmptyCount()
    {
        static::expectException(MatrixException::class);

        new Vector([]);
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(Vector::class, self::buildVector());
    }

    public function testGetSize()
    {
        $vector = self::buildVector();

        $this->assertEquals(4, $vector->getSize());
    }

    public function testSum()
    {
        $vector = self::buildVector();

        $this->assertEquals(10, $vector->sum());
    }

    public function testLength()
    {
        $vector = new Vector([
            [3, 4]
        ]);

        $this->assertEquals(5, $vector->length());
    }

    public function testDotProduct()
    {
        $vector1 = new Vector([
            [1,2,3,4]
        ]);
        $vector2 = new Vector([
            [-1,3,5,2]
        ]);

        $this->assertEquals((1 * -1) + (2 * 3) + (3 * 5) + (4 * 2), $vector1->dotProduct($vector2));
    }

    public function testOuterProduct()
    {

    }

    public function testNormalize()
    {
        $vector = new Vector([
            [3,4]
        ]);

        $this->assertEquals(new Vector([[3/5, 4/5]]), $vector->normalize());
    }

    public function testL1Norm()
    {
        $this->assertEquals(10, self::buildVector()->l1Norm());
    }

    public function testL2Norm()
    {
        $vector = new Vector([
            [3, 4]
        ]);

        $this->assertEquals(5, $vector->l2Norm());
    }

    public function testProjection()
    {

    }
}
