# Serverless Chunk Upload

### Lambda Invocation Limitation

[https://docs.aws.amazon.com/lambda/latest/dg/gettingstarted-limits.html](https://docs.aws.amazon.com/lambda/latest/dg/gettingstarted-limits.html)
[https://docs.aws.amazon.com/apigateway/latest/developerguide/limits.html](https://docs.aws.amazon.com/apigateway/latest/developerguide/limits.html)

- AWS Gateway only allow HTTP API Payload Size of 10MB.
- AWS Lambda only allow Function Invocation payload size of 6MB.
-> So Using AWS Gateway and AWS Lambda Integration, max payload size is 6MB

### Simple Chunk Upload Protocol 

To be able to send larger payload, need to chunk the payload into smaller pieces. 
- Let's say we have JSON payload of 10MB
- Decode the JSON into string, and split it into 10 chunks of 1MB string
- Call the API 10 times with different chunk payload
  - chunk_data: current chunk
  - chunk_index: current chunk's index
  - total_chunks: total chunks (in this example = 10)
  - payload_hashed: the md5 value of original JSON
- BE will collect 10 chunks
- After the last chunk is collected, the actual payload will be concatenated and pass through Laravel pipeline
  - The previous chunks will be collected and saved in Redis Cache
  - Those request will be returned immediately after saving to Redis Cache

### UniqueId

Since `payload_hashed` will be used as Tag Name of Cache, so attempting to upload the same content multiple times may cause conflict between those upload

To avoid this unexpected behavior, always add extra data to original payload called `uniqueId`
- This could be any random string or current timestamp, which can be considered as unique between multiple uploads.
- This `uniqueId` data will be deducted after restoring the original data.

### How to use

```
composer require carropublic/serverless-chunk-payload
```

The middleware will be auto registered via ServiceProvider, which is also auto registered by Laravel Auto Discovery
