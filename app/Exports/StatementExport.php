<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StatementExport implements WithMultipleSheets
{
    public function __construct(private array $s) {}

    public function sheets(): array
    {
        return [
            new class($this->s) implements FromArray {
                public function __construct(private array $s) {}
                public function array(): array
                {
                    $rows = [];
                    $rows[] = ['Empleado', $this->s['employee']['name'] ?? '', 'Código', $this->s['employee']['code'] ?? ''];
                    $rows[] = ['Periodo', ($this->s['period']['from'] ?? '').' a '.($this->s['period']['to'] ?? '')];
                    $rows[] = ['Moneda', $this->s['currency'] ?? 'CRC', 'Tipo cambio', $this->s['exchange_rate'] ?? null];
                    $rows[] = [];

                    $rows[] = ['Resumen de horas'];
                    $rows[] = ['1x (incluye feriados no trabajados)', $this->s['hours']['regular_1x'] ?? 0];
                    $rows[] = ['Extra 1.5x', $this->s['hours']['overtime_15'] ?? 0];
                    $rows[] = ['Doble 2x', $this->s['hours']['double_20'] ?? 0];
                    $rows[] = [];

                    $rows[] = ['Ingresos'];
                    foreach (($this->s['incomes'] ?? []) as $i) {
                        $rows[] = [$i['label'], $i['amount']];
                    }
                    $rows[] = ['Total bruto', $this->s['total_gross'] ?? 0];
                    $rows[] = [];

                    $rows[] = ['Deducciones'];
                    foreach (($this->s['deductions'] ?? []) as $d) {
                        $rows[] = [$d['label'], $d['amount']];
                    }
                    $rows[] = ['Total deducciones', $this->s['total_deductions'] ?? 0];
                    $rows[] = [];

                    $rows[] = ['Neto', $this->s['net'] ?? 0];

                    return $rows;
                }
            },
        ];
    }
}
