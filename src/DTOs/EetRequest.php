<?php

namespace Pomocnik\Eet\DTOs;

use Illuminate\Support\Str;

class EetRequest
{
    public function __construct(
        public readonly string $eicPopl,
        public readonly string $idJednotky,
        public readonly string $idPokl,
        public readonly string $poradCis,
        public readonly string $datTrzby,
        public readonly string $celkTrzba,
        public readonly int $rezim = 0,
        public readonly bool $prvniZaslani = true,
        public readonly ?string $uuidZpravy = null,
        public readonly bool $overeni = false,
        public readonly ?string $urcenoCerpZuct = null,
        public readonly ?string $cerpZuct = null,
        public readonly ?string $eicPoverujiciho = null,
        public readonly ?bool $povereniVicePopl = null,
    ) {}

    public static function fromReceipt(array $data, string $unitId, string $terminalId): self
    {
        return new self(
            eicPopl: $data['eic_popl'],
            idJednotky: $unitId,
            idPokl: $terminalId,
            poradCis: $data['porad_cis'],
            datTrzby: $data['dat_trzby'] ?? now()->format('Y-m-d\TH:i:s'),
            celkTrzba: number_format((float) $data['celk_trzba'], 2, '.', ''),
            rezim: $data['rezim'] ?? 0,
            prvniZaslani: $data['prvni_zaslani'] ?? true,
            uuidZpravy: $data['uuid_zpravy'] ?? (string) Str::uuid(),
            overeni: $data['overeni'] ?? false,
            urcenoCerpZuct: isset($data['urceno_cerp_zuct']) ? number_format((float) $data['urceno_cerp_zuct'], 2, '.', '') : null,
            cerpZuct: isset($data['cerp_zuct']) ? number_format((float) $data['cerp_zuct'], 2, '.', '') : null,
            eicPoverujiciho: $data['eic_poverujiciho'] ?? null,
            povereniVicePopl: $data['povereni_vice_popl'] ?? null,
        );
    }

    /**
     * Vrati pole atributu pro podepsani (PKP) dle specifikace EET v4.
     * Radky jsou serazeny dle XSD poradi: eic_popl, id_jednotky, id_pokl,
     * porad_cis, dat_trzby, celk_trzba.
     */
    public function getSignData(): string
    {
        return implode('|', [
            $this->eicPopl,
            $this->idJednotky,
            $this->idPokl,
            $this->poradCis,
            $this->datTrzby,
            $this->celkTrzba,
        ]);
    }

    public function toArray(): array
    {
        return [
            'eic_popl' => $this->eicPopl,
            'id_jednotky' => $this->idJednotky,
            'id_pokl' => $this->idPokl,
            'porad_cis' => $this->poradCis,
            'dat_trzby' => $this->datTrzby,
            'celk_trzba' => $this->celkTrzba,
            'rezim' => $this->rezim,
            'prvni_zaslani' => $this->prvniZaslani,
            'uuid_zpravy' => $this->uuidZpravy,
            'overeni' => $this->overeni,
            'urceno_cerp_zuct' => $this->urcenoCerpZuct,
            'cerp_zuct' => $this->cerpZuct,
            'eic_poverujiciho' => $this->eicPoverujiciho,
            'povereni_vice_popl' => $this->povereniVicePopl,
        ];
    }
}
