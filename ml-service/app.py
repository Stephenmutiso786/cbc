"""Privacy-preserving CatBoost risk scoring service for CBE.

It accepts opaque references and nine numeric features only.  Laravel owns all
learner names, school identifiers and persistence; this service owns no PII.
"""
import os
from pathlib import Path
from typing import List

from catboost import CatBoostClassifier
from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel, Field

FEATURES = [
    "attendance_30d", "attendance_term", "recent_score", "term_score",
    "score_trend", "fee_balance_ratio", "days_overdue", "terms_enrolled",
    "missed_assessment_ratio",
]
MODEL_PATH = Path(os.getenv("MODEL_PATH", "/data/cbe-risk-model.cbm"))
API_KEY = os.getenv("ML_SERVICE_API_KEY", "")
app = FastAPI(title="CBE CatBoost Risk Service", version="1.0.0")
model: CatBoostClassifier | None = None

class Sample(BaseModel):
    reference_id: str = Field(min_length=8, max_length=128)
    features: List[float] = Field(min_length=9, max_length=9)
    label: int | None = Field(default=None, ge=0, le=1)

class Batch(BaseModel):
    samples: List[Sample] = Field(min_length=1)

def authorized(x_api_key: str = Header(default="")):
    if not API_KEY or not x_api_key or x_api_key != API_KEY:
        raise HTTPException(status_code=401, detail="invalid API key")

def load_model():
    global model
    if model is None and MODEL_PATH.exists():
        model = CatBoostClassifier()
        model.load_model(str(MODEL_PATH))
    return model

@app.get("/health")
def health():
    return {"status": "ok", "model_ready": load_model() is not None, "features": FEATURES}

@app.post("/train", dependencies=[Depends(authorized)])
def train(batch: Batch):
    global model
    if any(item.label is None for item in batch.samples):
        raise HTTPException(status_code=422, detail="training samples require labels")
    labels = [item.label for item in batch.samples]
    if len(set(labels)) < 2:
        raise HTTPException(status_code=422, detail="training needs both future-risk outcomes")
    model = CatBoostClassifier(iterations=250, depth=6, learning_rate=0.05, loss_function="Logloss", verbose=False, random_seed=42)
    model.fit([item.features for item in batch.samples], labels)
    MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    model.save_model(str(MODEL_PATH))
    return {"trained": len(batch.samples), "features": FEATURES}

@app.post("/predict", dependencies=[Depends(authorized)])
def predict(batch: Batch):
    fitted = load_model()
    if fitted is None:
        raise HTTPException(status_code=503, detail="model is not trained")
    probabilities = fitted.predict_proba([item.features for item in batch.samples])[:, 1]
    importances = fitted.get_feature_importance()
    return {"predictions": [
        {"reference_id": item.reference_id, "risk_score": round(float(score) * 100, 1),
         "risk_level": "high" if score >= .65 else "medium" if score >= .35 else "low",
         "top_factors": [FEATURES[i] for i in sorted(range(len(FEATURES)), key=lambda i: abs(importances[i] * item.features[i]), reverse=True)[:3]]}
        for item, score in zip(batch.samples, probabilities)
    ]}
