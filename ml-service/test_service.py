"""Run: ML_SERVICE_API_KEY=test-key MODEL_PATH=/tmp/model.cbm python test_service.py"""
import os
from fastapi.testclient import TestClient
from app import app

client = TestClient(app)
key = {"X-API-Key": os.environ["ML_SERVICE_API_KEY"]}
low = [0.98, .97, 88, 84, 4, .02, 0, 6, .03]
high = [.45, .55, 38, 45, -7, .8, 80, 6, .7]
samples = [{"reference_id": f"low-{i:02d}-opaque", "features": low, "label": 0} for i in range(20)] + [{"reference_id": f"high-{i:02d}-opaque", "features": high, "label": 1} for i in range(20)]
assert client.get('/health').status_code == 200
assert client.post('/predict', json={"samples": samples[:1]}).status_code == 401
assert client.post('/train', headers=key, json={"samples": samples}).status_code == 200
result = client.post('/predict', headers=key, json={"samples": [{"reference_id": "struggling-opaque", "features": high}, {"reference_id": "strong-opaque", "features": low}]}).json()['predictions']
assert result[0]['risk_level'] == 'high' and result[1]['risk_level'] == 'low', result
print('health, authentication, training and high/low prediction passed')
