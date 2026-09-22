# CBE CatBoost Risk Service

Deploy this directory as its own Docker service and attach a persistent disk at
`/data`. Set a long random `ML_SERVICE_API_KEY`; Laravel must use the exact
same key. The service accepts only an opaque reference ID and nine numbers.

Health check: `GET /health`. Laravel calls authenticated `POST /train` and
`POST /predict`. Do not expose the service publicly without a network rule and
the API key. Train once from Laravel after at least 30 learners have 90 days of
history: `php artisan risk:train-model`.

## Local verification

Create a virtual environment, install `requirements.txt`, then run:

```sh
ML_SERVICE_API_KEY=test-key MODEL_PATH=/tmp/cbe-risk-model.cbm python test_service.py
```

The test verifies health, rejection without an API key, CatBoost training, and
that a deliberately struggling vector scores high while a strong vector scores
low. For production, use the Dockerfile and a persistent `/data` disk.
