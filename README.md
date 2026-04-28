# Gates

Laravel-сервис: индексирует входящие ETH и ERC-20 переводы, отправляет выводы средств.
Подпись транзакций офлайн, в [Go wallet-service](https://github.com/gauffer/trustwallet-functional-core). Laravel мнемонику не видит.

PHP 8.3+, Laravel 12, MySQL 8.0+.

## E2E на Sepolia проверено

ETH и USDC, входящие и исходящие, всё проверено вручную на живой сети. Реальные блоки, реальные транзакции.

| | Результат | Блок | Tx |
|---|---|---|---|
| ETH входящий | 0.05 ETH от Google Cloud Faucet, проиндексирован | 10748539 | |
| USDC входящий | 20 USDC от Circle Faucet, проиндексирован | 10782891 | [`0x34bc36...`](https://sepolia.etherscan.io/tx/0x34bc36675ba7d1c7715d285641dd1651f6b36aa6fd189babdcffaae136ebdd0c) |
| ETH исходящий | 0.001 ETH, BROADCASTED, подтверждён | 10782874 | [`0xc67165...`](https://sepolia.etherscan.io/tx/0xc671651942320889f7602d717d8e63166479317850f2114b760398e693c08068) |
| USDC исходящий | 1 USDC, BROADCASTED, подтверждён | 10782900 | [`0xa67706...`](https://sepolia.etherscan.io/tx/0xa6770690caad7ff3ab5ff3e1862d2f33e4b0fc237595ae313d8299106d445533) |

Индексер корректно обработал реорги (проверка `parent_hash`). Невалидный адрес на withdrawal возвращает 422 без попытки подписи.

## Архитектура

```
blockchain:index (artisan)
  eth_getBlockByNumber → проверка parent_hash (реорг) → сохранить блок
  ETH депозиты:   tx.to ∈ наши адреса → Transaction
  ERC-20 депозиты: eth_getLogs Transfer → Transaction

POST /api/withdrawals
  validateaddress (Go) → bcmath в wei → sign (Go) → eth_sendRawTransaction → BROADCASTED
```

Паттерн: **fat Active Record**, логика живёт на моделях, контроллеры и команды только оркестрируют.
Гейт это либо BASE (сеть + нативный актив), либо ASSET (ERC-20, дочерний). RPC-URL только у BASE.

## Запуск

```bash
# 0. Go wallet-service должен быть запущен (см. https://github.com/gauffer/trustwallet-functional-core)

# 1. MySQL
docker compose up -d

# 2. Laravel
composer install
cp .env.example .env          # прописать ETH_SEPOLIA_RPC_URL
php artisan key:generate
php artisan migrate
php artisan db:seed --class=GateSeeder
php artisan serve --port=8080
```

| Переменная | Где | Назначение |
|---|---|---|
| `ETH_SEPOLIA_RPC_URL` | `.env` | RPC Sepolia |
| `WALLET_MNEMONIC_ETHEREUM` | env оболочки | мнемоника: читает Go, не Laravel |
| `WALLET_SERVICE_URL` | `.env` | адрес Go-сервиса (по умолчанию `http://127.0.0.1:8000`) |

## Использование

```bash
# Создать адрес
php artisan address:create --base_gate=eth_sepolia

# Индексировать
php artisan blockchain:index --base_gate=eth_sepolia

# Вывод ETH
curl -X POST http://localhost:8080/api/withdrawals \
  -H "Content-Type: application/json" \
  -d '{"asset_gate":"eth_sepolia","to_address":"0x...","amount":"0.001"}'
```

