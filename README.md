# Receiptor

Receiptor is a personal receipt analysis service built with Symfony 8.

The application accepts receipt images, stores them in MinIO, extracts text using Tesseract OCR, analyzes the OCR result with Groq, and saves structured receipt data in PostgreSQL.

Processing is asynchronous and split into independent stages using Symfony Messenger and RabbitMQ. Multiple OCR workers can process receipts concurrently, while Groq and cancellation have their own dedicated workers and queues.

The project is designed as a practical backend project demonstrating asynchronous processing, queue-based concurrency, external services, object storage, OCR, LLM integration, transactional state changes, row-level locking, real-time events, and containerized infrastructure.

## Features

* Upload one or multiple receipt images
* Upload up to 10 receipt images in a single request
* Validate uploaded files before processing
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
* RabbitMQ-based message transports
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

<p align="center">
   <img src="docs/architecture.png" alt="Architecture" width="1200">
</p>

## Asynchronous processing

Symfony Messenger is used to decouple HTTP requests from potentially slow receipt processing.  
The application uses three independent RabbitMQ transports:

```dotenv
MESSENGER_OCR_TRANSPORT_DSN="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@rabbitmq:5672/%2f"
MESSENGER_GROQ_TRANSPORT_DSN="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@rabbitmq:5672/%2f"
MESSENGER_CANCEL_TRANSPORT_DSN="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@rabbitmq:5672/%2f"
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

Multiple workers consuming the same RabbitMQ queue act as competing consumers: each message is delivered to one available consumer.

## Concurrency and cancellation

Receipt processing can involve several concurrent operations:

* OCR processing
* Groq analysis
* receipt cancellation

A cancellation request can arrive while OCR or Groq processing is already running.
The application therefore does not rely only on in-memory entity state.

State-changing operations use database transactions and row-level locking. Before committing a processing result, the worker obtains a pessimistic lock on the receipt and checks its current status.

Conceptually:
<p align="center">
    <img src="docs/cancellation.png" alt="Cancellation" width="500">
</p>

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
The client can subscribe to the `receipts` topic and receive events for all receipts.

## Requirements

The project requires:

* Docker
* Docker Compose
* Git

The following services run inside Docker:

| Service             | Version                        |
|---------------------|--------------------------------|
| PHP                 | 8.4.26                         |
| Symfony             | 8.1.7                          |
| Nginx               | 1.29.5                         |
| PostgreSQL          | 16                             |
| Redis               | 8                              |
| RabbitMQ            | 4-management                   |
| MinIO               | `RELEASE.2025-04-22T22-12-26Z` |
| Mercure             | v0.24                          |
| Tesseract OCR       | system package                 |
| Prometheus          | 3.15.0                         |
| Grafana             | 13.2.3                         |
| cAdvisor            | v0.55.1                        |
| Node Exporter       | v1.12.1                        |
| PostgreSQL Exporter | v0.20.1                        |
| Redis Exporter      | v1.93.0                        |

Symfony dependencies are installed through Composer according to `composer.lock`.

A Groq API key is required for LLM-based receipt analysis.

## Installation

Clone the repository:

```bash
git clone <repository-url>
cd receiptor
```

Create the environment configuration:

```bash
cp .env.example .env
```

Replace the `change_me` placeholders with your local credentials, including `GROQ_API_KEY`.

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
Use the same values for `MINIO_ACCESS_KEY` / `MINIO_SECRET_KEY` and `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD`.

```dotenv
MINIO_ENDPOINT=http://minio:9000
MINIO_ACCESS_KEY=your_minio_access_key
MINIO_SECRET_KEY=your_minio_secret_key
MINIO_ROOT_USER=your_minio_access_key
MINIO_ROOT_PASSWORD=your_minio_secret_key
MINIO_BUCKET=receipts
MINIO_REGION=us-east-1
```

### RabbitMQ Messenger transports

```dotenv
MESSENGER_OCR_TRANSPORT_DSN="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@rabbitmq:5672/%2f"
MESSENGER_GROQ_TRANSPORT_DSN="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@rabbitmq:5672/%2f"
MESSENGER_CANCEL_TRANSPORT_DSN="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@rabbitmq:5672/%2f"
```

### Mercure

```dotenv
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:8081/.well-known/mercure
MERCURE_JWT_SECRET=your_secret
```

API keys and other sensitive configuration should be stored in the local `.env` file or another environment-specific secret mechanism. The `.env` file is ignored by Git and must not be committed.

## Build

Build the PHP image:

```bash
docker compose build
```

Install Composer dependencies:

```bash
docker compose run --rm --no-deps php composer install
```

> [!TIP]
> The `--no-deps` option prevents Docker Compose from starting PostgreSQL, Redis, and other dependent services just to install Composer dependencies.

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

Grafana:

```text
http://localhost:8008
```

Prometheus:

```text
http://localhost:9090
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
A maximum of **10 files** can be uploaded in a single request.

Each uploaded file must satisfy the following requirements:

| Requirement                         | Limit                     |
|-------------------------------------|---------------------------|
| Maximum number of files per request | 10                        |
| Maximum file size                   | 2 MiB per file            |
| Allowed MIME types                  | `image/jpeg`, `image/png` |
| Maximum original filename length    | 255 characters            |

The application validates every uploaded file before creating any receipt records. If at least one file fails
validation, the request is rejected and no receipts are created from that request.

Validation includes:

* checking that the upload completed successfully;
* checking the individual file size;
* checking the detected MIME type;
* checking the original filename length.

The application also performs the same validation when creating a receipt as a defensive measure.

Example with one file:

```bash
curl -X POST http://localhost:8080/receipts \
  -F "receipts[]=@/path/to/receipt.jpg"
```

Example with multiple files:

```bash
curl -X POST http://localhost:8080/receipts \
  -F "receipts[]=@/path/to/receipt1.jpg" \
  -F "receipts[]=@/path/to/receipt2.jpg" \
  -F "receipts[]=@/path/to/receipt3.jpg"
```

All uploaded files must be provided using the `receipts[]` field.  
The request creates the receipts, stores the files in MinIO, and dispatches OCR messages for asynchronous processing.  
The response contains the receipts created by the request as `ReceiptDto` objects.

A receipt is represented as:

```json
{
    "id": integer,
    "originalFilename": string,
    "status": string,
    "uploadedAt": string,
    "ocrProcessedAt": string,
    "groqProcessedAt": string,
    "ocrText": string,
    "purchasedAt": string,
    "merchant": string,
    "inn": string,
    "totalAmount": integer,
    "items": [
        {
            "id": integer,
            "name": string,
            "quantity": integer,
            "unitPrice": integer,
            "totalPrice": integer
        }
    ]
}
```

### Get receipts

```http
GET /receipts?lastId={lastId}
```

Example:

```bash
curl http://localhost:8080/receipts?lastId=10
```

Returns a list of receipts together with pagination information:

```json
{
    "receipts": array,
    "nextLastId": integer,
    "hasMore": bool
}
```

The endpoint uses the ID of the last received receipt as a cursor.

The response contains:

* `receipts` — the current page
* `nextLastId` — ID to use for the next request
* `hasMore` — whether more receipts are available

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

The cancel worker then updates the receipt state inside a transaction using row-level locking.  
After cancellation, a Mercure event is published to notify connected clients.

> [!NOTE]
> There are no separate HTTP endpoints for OCR or Groq processing. These stages are triggered internally through Symfony Messenger.

### API summary

| Method  | Route                          | Description                                    |
|---------|--------------------------------|------------------------------------------------|
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

## Monitoring

Receiptor includes a monitoring stack based on Prometheus and Grafana.

Prometheus collects application and infrastructure metrics, while Grafana provides dashboards for monitoring receipt processing, asynchronous queues, workers, and container resources.

The monitoring stack includes:

- Prometheus — metrics collection and storage
- Grafana — dashboards and visualization
- cAdvisor — container resource metrics
- Node Exporter — host resource metrics
- PostgreSQL Exporter — database metrics
- RabbitMQ Prometheus endpoint — broker and queue metrics

The Symfony application also exposes custom Prometheus metrics for receipt uploads, processing results, cancellations, and processing durations.

### Application metrics

The application exposes Prometheus metrics for:

- uploaded receipts
- processed receipts
- canceled receipts
- OCR processing duration
- Groq processing duration
- full receipt processing duration

Processing duration metrics are represented as Prometheus histograms, allowing Grafana to display both average processing time and percentile-based latency such as P95.

### Queue metrics

RabbitMQ exposes Prometheus metrics through its built-in metrics endpoint at `rabbitmq:15692/metrics/per-object`.

The dashboard monitors queue depth for the following processing queues:

- `ocr` — receipt OCR processing
- `groq` — receipt analysis using Groq
- `cancel` — receipt cancellation

Queue depth represents the number of messages waiting to be processed. Queue metrics help identify backlogs and processing bottlenecks.

RabbitMQ metrics are collected directly by Prometheus. No separate RabbitMQ exporter container is used.

Redis runs as a separate infrastructure service but is not used as the Symfony Messenger transport and is not currently scraped by Prometheus.

### Infrastructure metrics

cAdvisor provides container-level resource metrics such as:

- CPU usage
- memory usage

PostgreSQL Exporter provides PostgreSQL database metrics.

### Grafana dashboard

The main Grafana dashboard contains the following sections:

1. **Application**
    - total number of uploaded receipts
    - total number of processed receipts
    - total number of canceled receipts
    - receipt upload rate
    - receipt processing rate

2. **Processing**
    - average processing duration includes OCR, Groq and full receipt processing
    - P95 processing duration includes OCR, Groq and full receipt processing

3. **RabbitMQ queues and message processing**
    - number of consumers per queue
    - number of messages in the ocr, groq, and cancel queues, including ready and unacknowledged messages
    - published, delivered, acknowledged, and redelivered message rates

> [!WARNING]
> Number of consumers always shows 0 because Symfony Messenger uses AMQP::get instead of AMQP::consume.  
> This is a known issue in Symfony 8.1 and will be fixed in Symfony 8.2.

4. **Infrastructure**
    - number of RabbitMQ connections and channels
    - RabbitMQ process memory usage
    - number of active database connections
    - transaction commit and rollback rates

5. **System**
    - CPU usage per container
    - memory usage per container
    - host CPU usage
    - host memory usage

Grafana is intended as the operational interface for observing the application. It is available separately from the Receiptor API.

The dashboard uses Prometheus as its data source. Metrics are collected from the Receiptor application, RabbitMQ, PostgreSQL Exporter, cAdvisor, and Node Exporter.  
Redis is used as a storage for Prometheus metrics.

Grafana:

```text
http://localhost:8008
```

Prometheus:

```text
http://localhost:9090
```

RabbitMQ management UI:

```text
http://localhost:15672
```

### Dashboard provisioning

The Receiptor dashboard is provisioned from files in the repository:

```text
monitoring/grafana/
├── dashboards/
│   └── receiptor.json
└── provisioning/
    ├── dashboards/
    │   └── receiptor.yml
    └── datasources/
        └── prometheus.yml
```

Grafana loads the dashboard definition and Prometheus data source from these files when the stack starts. Treat `monitoring/grafana/dashboards/receiptor.json` as the version-controlled source of truth. After editing a dashboard in the Grafana UI, export/save the updated JSON and copy the intended changes back into this file, then commit it to Git. A dashboard edited only in the UI may not be reproducible after the provisioned file is restored.

The dashboard can be used during development and load testing to observe how queue depth, worker count, processing latency, and container resource usage change under load.

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
docker compose up --scale ocr-worker=4
```

## Why the project uses separate workers

Receipt processing consists of operations with different performance characteristics.

OCR is CPU-intensive and can be executed by multiple workers. Multiple OCR workers can consume messages from the same Redis transport concurrently, allowing OCR processing to scale horizontally.

Groq requests are external network operations and are isolated into a separate queue with a dedicated worker. This keeps external API processing independent of CPU-intensive OCR processing.

Cancellation is also isolated into a dedicated queue and worker. This allows cancellation requests to be processed independently of OCR and Groq workloads.

This separation allows each processing stage to be scaled independently.

For example, if OCR becomes the bottleneck, additional OCR workers can be started without creating additional Groq workers.

The architecture therefore demonstrates a practical form of horizontal worker scaling while keeping the application itself as a single Symfony application.

## License

This project is a personal portfolio project.

### Third-party software and services

Receiptor uses the following third-party software and services, which are subject to their respective licenses and terms:

| Technology | License / Terms |
|-------------------------------------------------------------------------------|--------------------------|
| [Symfony](https://symfony.com/license) | MIT License |
| [PostgreSQL](https://www.postgresql.org/about/licence/) | PostgreSQL License |
| [Redis](https://redis.io/legal/licenses/) | RSALv2 / SSPLv1 / AGPLv3 |
| [MinIO](https://docs.min.io/license/) | MinIO Software License |
| [Tesseract OCR](https://github.com/tesseract-ocr/tesseract/blob/main/LICENSE) | Apache License 2.0 |
| [Mercure](https://github.com/dunglas/mercure) | MIT License |
| [Nginx](https://nginx.org/en/docs/license.html) | 2-clause BSD License |
| [Docker Compose](https://github.com/docker/compose/blob/main/LICENSE) | Apache License 2.0 |
| [Monolog](https://github.com/Seldaek/monolog/blob/main/LICENSE) | MIT License |
| [Prometheus](https://github.com/prometheus/prometheus/blob/main/LICENSE) | Apache License 2.0 |
| [Grafana](https://github.com/grafana/grafana/blob/main/LICENSE) | AGPLv3 |
| [cAdvisor](https://github.com/google/cadvisor/blob/master/LICENSE) | Apache License 2.0 |
| [Node Exporter](https://github.com/prometheus/node_exporter/blob/master/LICENSE) | Apache License 2.0 |
| [PostgreSQL Exporter](https://github.com/prometheus-community/postgres_exporter/blob/master/LICENSE) | Apache License 2.0 |
| [Redis Exporter](https://github.com/oliver006/redis_exporter/blob/master/LICENSE) | MIT License |
| [Groq](https://console.groq.com/docs/legal/services-agreement) | Groq Services Agreement |

The Receiptor source code does not grant any additional rights to use third-party software, services, trademarks, or APIs. Third-party components and services remain subject to their respective licenses, terms, and usage conditions.

Users deploying or modifying Receiptor are responsible for complying with the applicable licenses and terms of the third-party software and services they use.
