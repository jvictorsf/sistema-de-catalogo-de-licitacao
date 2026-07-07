<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/helpers.php';

function assert_same_value(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . ' Obtido: ' . var_export($actual, true));
    }
}

$cepAddress = supplier_lookup_address_from_data([
    'street' => 'Rua das Flores',
    'neighborhood' => 'Centro',
]);

assert_same_value('RUA DAS FLORES, CENTRO', $cepAddress, 'Endereco do CEP deve aceitar street e neighborhood.');

$cnpjAddress = supplier_lookup_address_from_data([
    'descricao_tipo_de_logradouro' => 'Avenida',
    'logradouro' => 'Paulista',
    'numero' => '100',
    'bairro' => 'Bela Vista',
]);

assert_same_value('AVENIDA PAULISTA, 100, BELA VISTA', $cnpjAddress, 'Endereco do CNPJ deve montar tipo, rua, numero e bairro.');
assert_same_value('SAO PAULO', supplier_lookup_city_from_data(['city' => 'SAO PAULO']), 'Cidade deve aceitar o campo city.');
assert_same_value('SP', supplier_lookup_state_from_data(['state' => 'SP']), 'UF deve aceitar o campo state.');

echo "SupplierLookupTest: OK\n";
