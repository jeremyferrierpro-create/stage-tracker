---
name: aidf-devops-engineering
description: Implement build, CI, deployment and observability changes with rollback and least privilege. Use for pipelines, containers, infrastructure and release automation.
metadata:
  aidf:
    id: devops-engineering
    version: 1.0.0
    phases: [implementation, release]
    documents: [TRD, SECURITY, PLAN]
---

# AIDF DevOps Engineering

## Procedure

1. Read the current pipeline, Dockerfile, deployment manifests and environment
   model. Do not introduce a second deployment path.
2. Define the change: build, test, artifact, deploy, verify, rollback.
3. Apply least privilege to tokens, service accounts and secrets.
4. Pin tool and action versions; avoid floating tags.
5. Add caching only when measured; do not hide correctness behind cache.
6. Make the pipeline fail closed: a failed test or scan blocks the deploy.
7. Verify on a branch or staging environment before production.
8. Document rollback and test it where feasible.

## Checklist

- [ ] Build is reproducible from a clean checkout.
- [ ] Tests run in CI with the same command as local.
- [ ] Secrets are injected, never committed.
- [ ] Artifact is versioned and traceable to a commit.
- [ ] Deployment has a health check and a rollback path.
- [ ] Logs and metrics are available after deploy.
- [ ] Permissions are scoped to the environment.

## Hard limits

- An agent must not deploy to production or change cloud permissions without
  explicit human authorization.
- A green pipeline is not proof that the application is healthy; verify the
  deployed behaviour.
