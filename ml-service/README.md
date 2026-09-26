# CBE local CatBoost runtime

CatBoost runs inside the CBE application deployment. Laravel invokes
`cli.py` through a local process; it does not use an HTTP endpoint, service
URL, shared API key, or any external AI API.

Install the local dependency once on each application server:

```sh
python3 -m venv /opt/cbe-catboost
/opt/cbe-catboost/bin/pip install catboost>=1.2,<1.3
```

Set `CATBOOST_PYTHON_BINARY=/opt/cbe-catboost/bin/python` in the application
environment. The trained model is stored at
`storage/app/ml/cbe-risk-model.cbm`, so it remains with the application
storage. Train it after at least 30 learners have 90+ days of history:

```sh
php artisan risk:train-model
php artisan risk:predict
```

The local runtime receives only opaque references and the nine numeric learner
features. Learner names, contacts, and school identifiers are never sent to a
network service.
