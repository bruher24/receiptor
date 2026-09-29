# FlameSentry

FlameSentry is a personal receipt analysis service built with Symfony 8.  
The application accepts receipt images, stores them in MinIO, extracts text using Tesseract OCR, analyzes the OCR result with Groq, and saves structured receipt data in PostgreSQL.  
Processing is asynchronous and split into independent stages using Symfony Messenger and Redis. Multiple OCR workers can process receipts concurrently, while Groq and cancellation have their own dedicated workers and queues.  
The project is designed as a practical backend project demonstrating asynchronous processing, queue-based concurrency, external services, object storage, OCR, LLM integration, transactional state changes, row-level locking, real-time events, and containerized infrastructure.

## Features

* Upload one or multiple receipt images
* Store uploaded files in MinIO
* OCR processing with Tesseract
* Receipt analysis with Groq
* Extraction of:
    * purchase date
    * merchant
    * INN
    * total amount
    * purchased items
    * item quantity
    * unit price
    * total item price
* Asynchronous processing with Symfony Messenger
* Redis-based message transports
* Separate queues for OCR, Groq, and cancellation
* Multiple OCR workers running concurrently
* Asynchronous receipt cancellation
* Transactional state updates
* PostgreSQL row-level locking for concurrent operations
* Protection against processing canceled receipts
* Real-time processing events through Mercure
* Receipt processing timestamps
* Cursor-like pagination by receipt ID
* Fully containerized development environment with Docker Compose

## Architecture

```text
                         ┌───────────────┐
                         │    Client     │
                         └───────┬───────┘
                                 │
                                 ▼
                         ┌───────────────┐
                         │     Nginx     │
                         └───────┬───────┘
                                 │
                                 ▼
                         ┌───────────────┐
                         │    Symfony    │
                         │      API      │
                         └───────┬───────┘
                                 │
                   ┌─────────────┼─────────────┐
                   │             │             │
                   ▼             ▼             ▼
              PostgreSQL      MinIO         Redis
                                             │
                         ┌───────────────────┼───────────────────┐
                         │                   │                   │
                         ▼                   ▼                   ▼
                   OCR transport       Groq transport     Cancel transport
                         │                   │                   │
                  ┌──────┴──────┐            │                   │
                  │             │            │                   │
                  ▼             ▼            ▼                   ▼
              OCR worker    OCR worker   Groq worker       Cancel worker
                  │             │            │                   │
                  └──────┬──────┘            │                   │
                         │                   │                   │
                         ▼                   ▼                   ▼
                    Tesseract             Groq API          PostgreSQL
                         │
                         ▼
                  OCR result → Groq
                         │
                         ▼
                    PostgreSQL
                         │
                         ▼
                      Mercure
                         │
                         ▼
                       Client
```

## Processing pipeline

A receipt goes through the following pipeline:

```text
Upload
  │
  ▼
Store image in MinIO
  │
  ▼
Create Receipt
  │
  ▼
Dispatch OCR message
  │
  ▼
Redis OCR queue
  │
  ▼
OCR worker
  │
  ├── Read image from MinIO
  ├── Run Tesseract
  └── Save OCR result
  │
  ▼
Dispatch Groq message
  │
  ▼
Redis Groq queue
  │
  ▼
Groq worker
  │
  ├── Analyze OCR text
  └── Save structured receipt data
  │
  ▼
Publish Mercure event
```

Cancellation uses a separate asynchronous path:

```text
PATCH /receipts/{receiptId}/cancel
              │
              ▼
      Cancel message
              │
              ▼
       Redis cancel queue
              │
              ▼
        Cancel worker
              │
              ▼
     Transaction + row lock
              │
              ▼
      Receipt → canceled
              │
              ▼
      Mercure notification
```

## Asynchronous processing

Symfony Messenger is used to decouple HTTP requests from potentially slow receipt processing.

The application uses three independent Redis transports:

```dotenv
MESSENGER_OCR_TRANSPORT_DSN=redis://redis:6379/messages_ocr
MESSENGER_GROQ_TRANSPORT_DSN=redis://redis:6379/messages_groq
MESSENGER_CANCEL_TRANSPORT_DSN=redis://redis:6379/messages_cancel
```

The corresponding workers are:

```text
ocr-worker
groq-worker
cancel-worker
```

Each worker consumes only its own transport.

This allows different processing stages to scale independently.

For example, OCR is CPU-intensive and can be scaled independently:

```bash
docker compose up --scale ocr-worker=4 --scale groq-worker=1 --scale cancel-worker=1
```

This starts four OCR consumers, one Groq consumer, and one cancellation consumer.

Multiple workers consuming the same Redis transport act as competing consumers: each message is processed by one available worker.

This is queue-level work distribution rather than HTTP load balancing.

## Concurrency and cancellation

Receipt processing can involve several concurrent operations:

* OCR processing
* Groq analysis
* receipt cancellation

A cancellation request can arrive while OCR or Groq processing is already running.

The application therefore does not rely only on in-memory entity state.

State-changing operations use database transactions and row-level locking. Before committing a processing result, the worker obtains a pessimistic lock on the receipt and checks its current status.

Conceptually:

```text
Worker A                         Worker B
--------                         --------
Process receipt                  Cancel receipt
      │                                │
      ▼                                ▼
Acquire row lock                Wait for row lock
      │                                │
      ▼                                │
Check status                         │
      │                                │
      ▼                                │
Save result                            │
      │                                │
      ▼                                │
Commit                                  │
                                       ▼
                                 Acquire row lock
                                       │
                                       ▼
                                 Check status
                                       │
                                       ▼
                                  Mark canceled
```

If cancellation has already been committed, a later processing result is discarded instead of overwriting the canceled state.

This makes cancellation safe even when OCR or Groq processing is already in progress.

## Services

The application separates infrastructure and domain responsibilities into dedicated services.

### File storage

Uploaded files are stored in MinIO through a storage abstraction.

The application does not depend directly on the local filesystem for receipt storage.

### OCR

Tesseract is used to convert receipt images into text.

OCR processing runs asynchronously in dedicated workers.

### Receipt analysis

Groq is used to transform OCR text into structured receipt information.

The result is mapped into the receipt domain model.

### Messaging

Symfony Messenger provides asynchronous communication between processing stages.

Redis is used as the message transport.

### Real-time events

Mercure publishes receipt processing events to connected clients.

The client can subscribe to the `receipts` topic and receive events for all receipts instead of creating a separate subscription for every receipt.

## Requirements

The project requires:

* Docker
* Docker Compose
* Git

The following services run inside Docker:

* PHP 8.4
* Symfony 8.1
* Nginx
* PostgreSQL 16
* Redis 8
* MinIO
* Mercure
* Tesseract OCR

A Groq API key is required for LLM-based receipt analysis.

## Installation

Clone the repository:

```bash
git clone <repository-url>
cd flamesentry
```

Create the environment configuration:

```bash
cp .env .env.local
```

Add the required API credentials to `.env.local`:

```dotenv
GROQ_API_KEY=your_groq_api_key
```

Do not commit `.env.local` or API credentials to the repository.

## Build

Build the PHP image:

```bash
docker compose build
```

Install Composer dependencies:

```bash
docker compose run --rm --no-deps php composer install
```

The `--no-deps` option prevents Docker Compose from starting PostgreSQL, Redis, and other dependent services just to install Composer dependencies.

Alternatively, the Makefile provides:

```bash
make build
```

## Database

Start the database and run migrations:

```bash
docker compose up -d database
docker compose exec php php bin/console doctrine:migrations:migrate
```

Or use:

```bash
make migrate
```

To generate a new migration after changing Doctrine entities:

```bash
make migration
```

## Start the application

Start all services and workers:

```bash
docker compose up
```

For the development configuration with multiple OCR workers:

```bash
docker compose up --scale ocr-worker=4 --scale groq-worker=1 --scale cancel-worker=1
```

The Makefile provides the same configuration:

```bash
make up
```

The API is available at:

```text
http://localhost:8080
```

Mercure is available at:

```text
http://localhost:8081
```

MinIO API:

```text
http://localhost:9000
```

MinIO console:

```text
http://localhost:9001
```

## Useful Make commands

Build the project:

```bash
make build
```

Create a migration:

```bash
make migration
```

Apply migrations:

```bash
make migrate
```

Generate an entity:

```bash
make entity
```

Start the application with workers:

```bash
make up
```

Clear Symfony cache:

```bash
make clear
```

## API

### Upload receipts

```http
POST /receipts
```

Accepts one or multiple uploaded receipt images.

Example with one file:

```bash
curl -X POST http://localhost:8080/receipts \
  -F "receipt=@/path/to/receipt.jpg"
```

Example with multiple files:

```bash
curl -X POST http://localhost:8080/receipts \
  -F "receipt1=@/path/to/receipt1.jpg" \
  -F "receipt2=@/path/to/receipt2.jpg" \
  -F "receipt3=@/path/to/receipt3.jpg"
```

The uploaded field names are not fixed. The controller processes all uploaded files from the request.

The request creates the receipts, stores the files in MinIO, and dispatches OCR messages for asynchronous processing.

The response contains the receipts created by the request as `ReceiptDto` objects.

A receipt contains:

```text
id
originalFilename
storagePath
status
uploadedAt
ocrProcessedAt
groqProcessedAt
ocrText
purchasedAt
merchant
inn
totalAmount
items
```

Each item contains:

```text
id
name
quantity
unitPrice
totalPrice
```

### Get receipts

```http
GET /receipts
```

Example:

```bash
curl http://localhost:8080/receipts
```

Returns a list of receipts together with pagination information:

```json
{
    "receipts": [],
    "nextLastId": 10,
    "hasMore": true
}
```

### Pagination

```http
GET /receipts?lastId=10
```

The endpoint uses the ID of the last received receipt as a cursor.

The response contains:

* `receipts` — the current page
* `nextLastId` — ID to use for the next request
* `hasMore` — whether more receipts are available

Example:

```bash
curl "http://localhost:8080/receipts?lastId=10"
```

### Get a receipt

```http
GET /receipts/{receiptId}
```

Example:

```bash
curl http://localhost:8080/receipts/1
```

Returns a single `ReceiptDto`.

### Cancel receipt processing

```http
PATCH /receipts/{receiptId}/cancel
```

Example:

```bash
curl -X PATCH http://localhost:8080/receipts/1/cancel
```

The cancellation request is asynchronous.

The controller dispatches a cancellation message to the dedicated cancel queue and immediately returns:

```text
202 Accepted
```

with an empty JSON array:

```json
[]
```

The cancel worker then updates the receipt state inside a transaction using row-level locking.

After cancellation, a Mercure event is published to notify connected clients.

There are no separate HTTP endpoints for OCR or Groq processing. These stages are triggered internally through Symfony Messenger.

### API summary

| Method  | Route                          | Description                                    |
| ------- | ------------------------------ | ---------------------------------------------- |
| `GET`   | `/receipts`                    | Get receipts with optional `lastId` pagination |
| `POST`  | `/receipts`                    | Upload one or multiple receipts                |
| `GET`   | `/receipts/{receiptId}`        | Get a receipt by ID                            |
| `PATCH` | `/receipts/{receiptId}/cancel` | Cancel receipt processing asynchronously       |

## Mercure

Mercure is used to notify clients about changes in receipt processing.

The application publishes events to the:

```text
receipts
```

topic.

The frontend subscribes once to this topic and receives events for all receipts.

Public Mercure endpoint:

```text
http://localhost:8081/.well-known/mercure
```

Example subscription:

```javascript
const url = new URL('http://localhost:8081/.well-known/mercure');

url.searchParams.append('topic', 'receipts');

const eventSource = new EventSource(url);

eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);

    console.log(data);
};
```

A processing event contains information about the affected receipt and its current state.

For example:

```json
{
    "type": "receipt.cancelled",
    "receiptId": 1,
    "status": "canceled",
    "statusText": "Отменено"
}
```

The exact event payload depends on the event being published.

## Receipt lifecycle

A receipt is processed through several asynchronous stages.

Conceptually:

```text
pending
   │
   ▼
OCR processing
   │
   ▼
OCR completed
   │
   ▼
Groq processing
   │
   ▼
Groq completed
```

Cancellation can occur while processing is in progress:

```text
pending
   │
   ├──────────────► canceled
   │
   ▼
OCR processing
   │
   ├──────────────► canceled
   │
   ▼
Groq processing
   │
   └──────────────► canceled
```

A canceled receipt must not be overwritten by a successful result from a processing stage that was already running.

Database transactions and pessimistic row locks are used to enforce this rule.

## Project structure

A simplified project structure:

```text
src/
├── Controller/
│   └── ReceiptController.php
│
├── Dto/
│   ├── ReceiptDto.php
│   └── ReceiptItemDto.php
│
├── Entity/
│   ├── Receipt.php
│   └── ReceiptItem.php
│
├── Enum/
│   └── ReceiptStatus.php
│
├── Message/
│   ├── ProcessReceiptOcrMessage.php
│   ├── ProcessReceiptGroqMessage.php
│   └── ProcessReceiptCancelMessage.php
│
├── MessageHandler/
│   ├── ProcessReceiptOcrMessageHandler.php
│   ├── ProcessReceiptGroqMessageHandler.php
│   └── ProcessReceiptCancelMessageHandler.php
│
├── Repository/
│   └── ReceiptRepository.php
│
├── Service/
│   ├── ReceiptService.php
│   ├── ReceiptAnalyzerInterface.php
│   └── ...
│
└── Storage/
    ├── FileStorageInterface.php
    └── ...
```

Infrastructure configuration:

```text
config/
├── packages/
│   ├── doctrine.yaml
│   ├── messenger.yaml
│   ├── monolog.yaml
│   └── mercure.yaml
│
└── services.yaml

docker/
├── nginx/
│   └── default.conf
│
└── php/
    └── Dockerfile

compose.yaml
Makefile
```

## Configuration

The main infrastructure variables are configured through environment variables.

### PostgreSQL

```dotenv
DATABASE_URL="postgresql://symfony:your_postgres_password@database:5432/receiptanalyzer?serverVersion=16&charset=utf8"

POSTGRES_USER=symfony
POSTGRES_PASSWORD=your_postgres_password
POSTGRES_DB=receiptanalyzer
```

### MinIO

```dotenv
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=your_minio_credential
MINIO_SECRET_KEY=your_minio_credential
MINIO_BUCKET=receipts
MINIO_REGION=us-east-1
```

### Redis Messenger transports

```dotenv
MESSENGER_OCR_TRANSPORT_DSN=redis://redis:6379/messages_ocr
MESSENGER_GROQ_TRANSPORT_DSN=redis://redis:6379/messages_groq
MESSENGER_CANCEL_TRANSPORT_DSN=redis://redis:6379/messages_cancel
```

### Mercure

```dotenv
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:8081/.well-known/mercure
MERCURE_JWT_SECRET=your_secret
```

### Secrets

API keys and other sensitive configuration should be placed in `.env.local` or another environment-specific secret mechanism.

Secrets should not be committed to Git.

## Logging

The application uses Monolog.

In the development environment, application logs are written to:

```text
php://stderr
```

To follow the log from a worker container:

```bash
docker compose logs -f ocr-worker
```

Messenger console messages such as:

```text
Received message
Handling message
Handled message
```

are written to the worker process output as well.

## Monitoring workers

List running containers:

```bash
docker compose ps
```

View OCR worker logs:

```bash
docker compose logs -f ocr-worker
```

View Groq worker logs:

```bash
docker compose logs -f groq-worker
```

View cancellation worker logs:

```bash
docker compose logs -f cancel-worker
```

View all application services:

```bash
docker compose logs -f
```

The number of OCR workers can be changed without modifying the application:

```bash
docker compose up --scale ocr-worker=1
```

or:

```bash
docker compose up --scale ocr-worker=4
```

## Useful Docker commands

Start the complete stack:

```bash
docker compose up
```

Start in detached mode:

```bash
docker compose up -d
```

Stop the stack:

```bash
docker compose down
```

Rebuild containers:

```bash
docker compose build
```

Rebuild without cache:

```bash
docker compose build --no-cache
```

Show running containers:

```bash
docker compose ps
```

Open a shell inside the PHP container:

```bash
docker compose exec php sh
```

Run Symfony commands:

```bash
docker compose exec php php bin/console
```

Clear Symfony cache:

```bash
docker compose exec php php bin/console cache:clear
```

Inspect registered Messenger handlers:

```bash
docker compose exec php php bin/console debug:messenger
```

Inspect application logs:

```bash
docker compose exec php tail -f var/log/dev.log
```

## Development workflow

A typical development workflow is:

```bash
# Start infrastructure and workers
make up
```

Upload a receipt:

```bash
curl -X POST http://localhost:8080/receipts \
  -F "receipt=@/path/to/receipt.jpg"
```

Check the created receipt:

```bash
curl http://localhost:8080/receipts/1
```

Follow worker processing:

```bash
docker compose logs -f ocr-worker groq-worker
```

Follow application logs:

```bash
docker compose exec ocr-worker tail -f var/log/dev.log
```

If a receipt needs to be canceled:

```bash
curl -X PATCH http://localhost:8080/receipts/1/cancel
```

The cancellation is processed asynchronously by the cancel worker.

## Technology stack

| Technology        | Purpose                        |
| ----------------- | ------------------------------ |
| PHP 8.4           | Application runtime            |
| Symfony 8.1       | Backend framework              |
| Symfony Messenger | Asynchronous processing        |
| PostgreSQL 16     | Persistent data storage        |
| Redis 8           | Message transport              |
| MinIO             | Receipt image storage          |
| Tesseract OCR     | Text extraction                |
| Groq              | LLM-based receipt analysis     |
| Mercure           | Real-time client notifications |
| Nginx             | HTTP server / reverse proxy    |
| Docker Compose    | Local infrastructure           |
| Monolog           | Application logging            |

## Why the project uses separate workers

Receipt processing consists of operations with different performance characteristics.

OCR is CPU-intensive and can be executed by multiple workers:

```text
OCR queue
   │
   ├── OCR worker
   ├── OCR worker
   ├── OCR worker
   └── OCR worker
```

Groq requests are external network operations and are isolated into a separate queue:

```text
Groq queue
   │
   └── Groq worker
```

Cancellation is also isolated:

```text
Cancel queue
   │
   └── Cancel worker
```

This separation allows each processing stage to be scaled independently.

For example, if OCR becomes the bottleneck, additional OCR workers can be started without creating additional Groq workers.

The architecture therefore demonstrates a practical form of horizontal worker scaling while keeping the application itself as a single Symfony application.

## License

This project is a personal portfolio project.
