"""Local command runner for the CBE CatBoost model.

Laravel invokes this program through stdin/stdout, so the model remains part
of the deployed school application and does not require an HTTP API, URL or
shared API key.
"""
import json
import sys
from pathlib import Path

from catboost import CatBoostClassifier

FEATURES = ["attendance_30d", "attendance_term", "recent_score", "term_score", "score_trend", "fee_balance_ratio", "days_overdue", "terms_enrolled", "missed_assessment_ratio"]

def fail(message):
    print(json.dumps({"ok": False, "error": message}))
    raise SystemExit(1)

def main():
    try:
        request = json.load(sys.stdin)
        operation = request["operation"]
        samples = request["samples"]
        model_path = Path(request["model_path"])
        if not samples:
            fail("At least one numeric sample is required.")
        if any(len(item.get("features", [])) != len(FEATURES) for item in samples):
            fail("Each sample must contain exactly nine numeric features.")
        if operation == "train":
            labels = [item.get("label") for item in samples]
            if any(label not in (0, 1) for label in labels) or len(set(labels)) < 2:
                fail("Training requires labelled examples from both risk outcomes.")
            model = CatBoostClassifier(iterations=250, depth=6, learning_rate=.05, loss_function="Logloss", verbose=False, random_seed=42)
            model.fit([item["features"] for item in samples], labels)
            model_path.parent.mkdir(parents=True, exist_ok=True)
            model.save_model(str(model_path))
            print(json.dumps({"ok": True, "trained": len(samples), "features": FEATURES}))
            return
        if operation == "predict":
            if not model_path.exists():
                fail("The local CatBoost model has not been trained yet.")
            model = CatBoostClassifier()
            model.load_model(str(model_path))
            probabilities = model.predict_proba([item["features"] for item in samples])[:, 1]
            importances = model.get_feature_importance()
            predictions = []
            for item, score in zip(samples, probabilities):
                factors = sorted(range(len(FEATURES)), key=lambda i: abs(importances[i] * item["features"][i]), reverse=True)[:3]
                predictions.append({"reference_id": item["reference_id"], "risk_score": round(float(score) * 100, 1), "risk_level": "high" if score >= .65 else "medium" if score >= .35 else "low", "top_factors": [FEATURES[i] for i in factors]})
            print(json.dumps({"ok": True, "predictions": predictions}))
            return
        fail("Unknown local model operation.")
    except Exception as error:
        fail(str(error))

if __name__ == "__main__":
    main()
