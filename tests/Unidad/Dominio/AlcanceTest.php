<?php
namespace LibertyFin\Pruebas\Unidad\Dominio;

use LibertyFin\Dominio\Alcance;
use PHPUnit\Framework\TestCase;

/**
 * El alcance decide si un abogado ve el expediente de otro. Si esto se
 * rompe, el síntoma es una fuga de datos silenciosa: nadie recibe un
 * error, simplemente se ve de más.
 */
final class AlcanceTest extends TestCase
{
    public function testSinLimiteNoFiltra()
    {
        $a = Alcance::sinLimite();
        $this->assertTrue($a->esTotal());
        $this->assertSame('', $a->filtro('responsable_id'));
        $this->assertSame('1=1', $a->donde('responsable_id'));
        $this->assertSame([], $a->parametros());
    }

    public function testLimitadoFiltraPorColumna()
    {
        $a = Alcance::de(7);
        $this->assertFalse($a->esTotal());
        $this->assertSame(' AND responsable_id = :alc_responsable_id',
                          $a->filtro('responsable_id'));
        $this->assertSame(['alc_responsable_id' => 7],
                          $a->parametros('responsable_id'));
    }

    public function testFiltroConAliasDeTabla()
    {
        $a = Alcance::de(3);
        $this->assertSame(' AND c.responsable_id = :alc_responsable_id',
                          $a->filtro('responsable_id', 'c'));
    }

    /** Dos columnas distintas no se pisan los parámetros. */
    public function testDosColumnasUsanMarcadoresDistintos()
    {
        $a = Alcance::de(5);
        $this->assertNotSame($a->filtro('responsable_id'), $a->filtro('abogado_id'));
        $this->assertSame(['alc_abogado_id' => 5], $a->parametros('abogado_id'));
    }

    public function testAlcanzaSoloLoPropio()
    {
        $a = Alcance::de(7);
        $this->assertTrue($a->alcanza(7));
        $this->assertFalse($a->alcanza(8));
    }

    /**
     * El prospecto que acaba de entrar por WhatsApp todavía no tiene
     * responsable. Si nadie lo alcanzara, nadie podría tomarlo.
     */
    public function testSinResponsableLoAlcanzaCualquiera()
    {
        $a = Alcance::de(7);
        $this->assertTrue($a->alcanza(null));
        $this->assertTrue($a->alcanza(0));
    }

    public function testAdminAlcanzaTodo()
    {
        $a = Alcance::sinLimite();
        $this->assertTrue($a->alcanza(99));
        $this->assertTrue($a->alcanza(null));
    }

    /** El nombre de columna nunca entra crudo a la consulta. */
    public function testColumnaInvalidaRevienta()
    {
        $this->expectException(\InvalidArgumentException::class);
        Alcance::de(1)->filtro('responsable_id = 1 OR 1');
    }
}
