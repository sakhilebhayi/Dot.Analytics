# Docker Hardening & Kubernetes Guide

**Scorecard domain:** Infrastructure & Cloud (currently 65/100)
**Target:** 83/100

---

## Fix 1 — Non-Root Container User (Quick Win, 15 minutes)

The Dockerfile currently runs as root in the production stage. Fix:

```dockerfile
# In Dockerfile, at the end of the production stage:

# Ensure correct ownership
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/public \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Drop to non-root user
USER www-data

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

> **Note:** Supervisord itself may need to start as root to bind to port 80.
> Use `setuid www-data` in supervisord.conf for child processes, or use
> port 8080 internally and reverse proxy from nginx on the host.

---

## Fix 2 — Container Image Scanning

Add to `.github/workflows/ci.yml` build job:

```yaml
build:
  name: Docker Build & Scan
  runs-on: ubuntu-latest
  needs: [test]
  steps:
    - uses: actions/checkout@v4

    - name: Build Docker image
      uses: docker/build-push-action@v5
      with:
        context: .
        target: production
        push: false
        tags: dot-analytics:${{ github.sha }}
        load: true

    - name: Scan image for vulnerabilities (Trivy)
      uses: aquasecurity/trivy-action@master
      with:
        image-ref: 'dot-analytics:${{ github.sha }}'
        format: 'table'
        exit-code: '1'
        ignore-unfixed: true
        severity: 'CRITICAL,HIGH'
```

---

## Fix 3 — Kubernetes Manifests

Create `k8s/` directory with these files:

### `k8s/namespace.yaml`
```yaml
apiVersion: v1
kind: Namespace
metadata:
  name: dot-analytics
  labels:
    app.kubernetes.io/name: dot-analytics
```

### `k8s/deployment.yaml`
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: dot-analytics-app
  namespace: dot-analytics
spec:
  replicas: 2
  selector:
    matchLabels:
      app: dot-analytics-app
  template:
    metadata:
      labels:
        app: dot-analytics-app
    spec:
      securityContext:
        runAsNonRoot: true
        runAsUser: 33      # www-data
        fsGroup: 33
      containers:
        - name: app
          image: ghcr.io/sakhileb/dot-analytics:latest
          ports:
            - containerPort: 80
          envFrom:
            - secretRef:
                name: dot-analytics-secrets
          resources:
            requests:
              memory: "256Mi"
              cpu: "100m"
            limits:
              memory: "512Mi"
              cpu: "500m"
          livenessProbe:
            httpGet:
              path: /api/ping
              port: 80
            initialDelaySeconds: 30
            periodSeconds: 10
          readinessProbe:
            httpGet:
              path: /up
              port: 80
            initialDelaySeconds: 10
            periodSeconds: 5
          securityContext:
            allowPrivilegeEscalation: false
            readOnlyRootFilesystem: false
            capabilities:
              drop: [ALL]
```

### `k8s/hpa.yaml` (Horizontal Pod Autoscaler)
```yaml
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: dot-analytics-hpa
  namespace: dot-analytics
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: dot-analytics-app
  minReplicas: 2
  maxReplicas: 10
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70
    - type: Resource
      resource:
        name: memory
        target:
          type: Utilization
          averageUtilization: 80
```

### `k8s/secrets.yaml` (use Sealed Secrets or External Secrets Operator)
```yaml
# Do NOT store real secrets in git — use External Secrets Operator
# This is a template only
apiVersion: external-secrets.io/v1beta1
kind: ExternalSecret
metadata:
  name: dot-analytics-secrets
  namespace: dot-analytics
spec:
  secretStoreRef:
    name: aws-ssm-store
    kind: ClusterSecretStore
  target:
    name: dot-analytics-secrets
  data:
    - secretKey: APP_KEY
      remoteRef:
        key: /dot-analytics/production/APP_KEY
    - secretKey: ANTHROPIC_API_KEY
      remoteRef:
        key: /dot-analytics/production/ANTHROPIC_API_KEY
```

---

## Fix 4 — Network Policy

Restrict pod-to-pod communication:

```yaml
# k8s/network-policy.yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: dot-analytics-netpol
  namespace: dot-analytics
spec:
  podSelector:
    matchLabels:
      app: dot-analytics-app
  policyTypes:
    - Ingress
    - Egress
  ingress:
    - from:
        - podSelector:
            matchLabels:
              app: dot-analytics-nginx
      ports:
        - protocol: TCP
          port: 80
  egress:
    - to:
        - podSelector:
            matchLabels:
              app: dot-analytics-postgres
      ports:
        - protocol: TCP
          port: 5432
    - to:
        - podSelector:
            matchLabels:
              app: dot-analytics-redis
      ports:
        - protocol: TCP
          port: 6379
    # Allow external AI provider calls
    - to: []
      ports:
        - protocol: TCP
          port: 443
```

---

## Checklist

- [ ] `USER www-data` added to Dockerfile production stage
- [ ] Trivy image scanning added to CI build job
- [ ] `k8s/` folder created with namespace, deployment, service, HPA
- [ ] Kubernetes secrets use External Secrets Operator or Sealed Secrets
- [ ] Network policy created
- [ ] `securityContext.runAsNonRoot: true` set on all deployments
- [ ] `allowPrivilegeEscalation: false` set
- [ ] All capabilities dropped except what's explicitly needed
- [ ] Scorecard updated
