pipeline {
  agent { label 'docker' }

  options {
    timestamps()
    disableConcurrentBuilds()
    skipDefaultCheckout(true)
    buildDiscarder(logRotator(numToKeepStr: '20'))
  }

  environment {
    COMPOSE_PROJECT_NAME = "absensi-sppg-ci-${BUILD_NUMBER}"
    BACKEND_TEST_IMAGE = "absensi-sppg-backend-testing:${BUILD_NUMBER}"
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
        script {
          env.VCS_REF = sh(script: 'git rev-parse --short=12 HEAD', returnStdout: true).trim()
          env.IMAGE_TAG = "${env.BUILD_NUMBER}-${env.VCS_REF}"
        }
      }
    }

    stage('Backend Format') {
      steps {
        sh 'docker build --target testing --tag "$BACKEND_TEST_IMAGE" backend'
        sh 'docker run --rm "$BACKEND_TEST_IMAGE" vendor/bin/pint --test'
      }
    }

    stage('Backend Tests') {
      steps {
        sh 'mkdir -p backend/storage/test-results && rm -f backend/storage/test-results/junit.xml'
        sh 'BACKEND_TEST_IMAGE="$BACKEND_TEST_IMAGE" docker compose -f infra/docker-compose.test.yml up --abort-on-container-exit --exit-code-from test_runner --no-build'
      }
      post {
        always {
          sh '''
            runner=$(BACKEND_TEST_IMAGE="$BACKEND_TEST_IMAGE" docker compose -f infra/docker-compose.test.yml ps -aq test_runner)
            if [ -n "$runner" ]; then
              docker cp "$runner:/var/www/html/storage/test-results/junit.xml" backend/storage/test-results/junit.xml 2>/dev/null || true
            fi
          '''
          sh 'BACKEND_TEST_IMAGE="$BACKEND_TEST_IMAGE" docker compose -f infra/docker-compose.test.yml down --volumes --remove-orphans || true'
          junit allowEmptyResults: false, testResults: 'backend/storage/test-results/junit.xml'
        }
      }
    }

    stage('Frontend Checks') {
      steps {
        dir('frontend') {
          sh '''
            docker run --rm --volumes-from "$HOSTNAME" --user "$(id -u):$(id -g)" -e HOME=/tmp \
              -w "$PWD" node:20-alpine sh -ec '
              npm ci --ignore-scripts
              npm run lint
              npm run typecheck
              npm audit --omit=dev --audit-level=critical
            '
          '''
          script {
            if (fileExists('vitest.config.ts') || fileExists('vitest.config.js') || fileExists('jest.config.ts') || fileExists('jest.config.js')) {
              sh 'docker run --rm --volumes-from "$HOSTNAME" --user "$(id -u):$(id -g)" -e HOME=/tmp -w "$PWD" node:20-alpine npm test -- --ci'
            } else {
              echo 'Frontend automated tests belum dikonfigurasi; tahap dilewati.'
            }
          }
        }
      }
    }

    stage('Build Images') {
      steps {
        sh 'docker build --target production --build-arg APP_VERSION="$IMAGE_TAG" --build-arg VCS_REF="$VCS_REF" --tag "absensi-sppg-backend:$IMAGE_TAG" backend'
        sh 'docker build --build-arg APP_VERSION="$IMAGE_TAG" --build-arg VCS_REF="$VCS_REF" --build-arg NEXT_PUBLIC_API_BASE_URL="/api/v1" --tag "absensi-sppg-frontend:$IMAGE_TAG" frontend'
      }
    }

    stage('Validate Images') {
      steps {
        sh 'docker image inspect "absensi-sppg-backend:$IMAGE_TAG" "absensi-sppg-frontend:$IMAGE_TAG" >/dev/null'
        sh '''test "$(docker image inspect --format '{{ index .Config.Labels "org.opencontainers.image.revision" }}' "absensi-sppg-backend:$IMAGE_TAG")" = "$VCS_REF"'''
        sh '''test "$(docker image inspect --format '{{ index .Config.Labels "org.opencontainers.image.revision" }}' "absensi-sppg-frontend:$IMAGE_TAG")" = "$VCS_REF"'''
        sh '''
          docker run -d --name "absensi-backend-smoke-$BUILD_NUMBER" \
            -e APP_ENV=testing \
            -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
            "absensi-sppg-backend:$IMAGE_TAG"
          docker run -d --name "absensi-frontend-smoke-$BUILD_NUMBER" \
            "absensi-sppg-frontend:$IMAGE_TAG"

          for container in "absensi-backend-smoke-$BUILD_NUMBER" "absensi-frontend-smoke-$BUILD_NUMBER"; do
            status=starting
            for attempt in $(seq 1 30); do
              status=$(docker inspect --format='{{.State.Health.Status}}' "$container")
              [ "$status" = healthy ] && break
              [ "$status" = unhealthy ] && break
              sleep 2
            done
            if [ "$status" != healthy ]; then
              docker logs "$container"
              exit 1
            fi
          done
        '''
      }
      post {
        always {
          sh 'docker rm -f "absensi-backend-smoke-$BUILD_NUMBER" "absensi-frontend-smoke-$BUILD_NUMBER" 2>/dev/null || true'
        }
      }
    }

    stage('Deploy') {
      steps {
        sh '''
          set -eu
          DEPLOY_DIR="$JENKINS_HOME/absensi-sppg-deploy"
          ENV_FILE="$DEPLOY_DIR/.env"
          COMPOSE_FILE="$DEPLOY_DIR/docker-compose.yml"

          install -d -m 700 "$DEPLOY_DIR"
          install -m 600 infra/docker-compose.yml "$COMPOSE_FILE"
          if [ ! -f "$ENV_FILE" ]; then
            APP_KEY=$(docker run --rm "$BACKEND_TEST_IMAGE" php artisan key:generate --show --no-ansi)
            DB_ROOT_PASSWORD=*** run --rm alpine:3.21 sh -c 'tr -dc A-Za-z0-9 </dev/urandom | head -c 40')
            DB_PASSWORD=*** run --rm alpine:3.21 sh -c 'tr -dc A-Za-z0-9 </dev/urandom | head -c 40')
            umask 077
            cat > "$ENV_FILE" <<EOF
APP_ENV=production
APP_DEBUG=false
APP_KEY=$APP_KEY
APP_URL=http://192.168.1.60:3040
APP_VERSION=$IMAGE_TAG
VCS_REF=$VCS_REF
BACKEND_IMAGE=absensi-sppg-backend
FRONTEND_IMAGE=absensi-sppg-frontend
PUBLIC_API_BASE_URL=/api/v1
APP_BIND_ADDRESS=0.0.0.0
APP_PORT=3040
FRONTEND_PORT=3041
BACKEND_PORT=8040
DB_DATABASE=absensi_sppg
DB_USERNAME=sppg_user
DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD
DB_PASSWORD=$DB_PASSWORD
EOF
          fi

          sed -i "/^APP_VERSION=/c\\APP_VERSION=$IMAGE_TAG" "$ENV_FILE"
          sed -i "/^VCS_REF=/c\\VCS_REF=$VCS_REF" "$ENV_FILE"
          docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --quiet
          docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d --no-build --remove-orphans
          docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T backend php artisan migrate --force
        '''
      }
    }

    stage('Runtime Verification') {
      steps {
        sh '''
          set -eu
          DEPLOY_DIR="$JENKINS_HOME/absensi-sppg-deploy"
          for attempt in $(seq 1 30); do
            backend=$(curl -sS -o /dev/null -w '%{http_code}' http://192.168.1.60:3040/up 2>/dev/null || true)
            frontend=$(curl -sS -o /dev/null -w '%{http_code}' http://192.168.1.60:3040/ 2>/dev/null || true)
            api=$(curl -sS -o /dev/null -w '%{http_code}' http://192.168.1.60:3040/api/v1/public/employees 2>/dev/null || true)
            [ "$backend" = 200 ] && [ "$frontend" = 200 ] && [ "$api" = 200 ] && exit 0
            sleep 2
          done
          docker compose --env-file "$DEPLOY_DIR/.env" -f "$DEPLOY_DIR/docker-compose.yml" ps
          docker compose --env-file "$DEPLOY_DIR/.env" -f "$DEPLOY_DIR/docker-compose.yml" logs --tail=100
          exit 1
        '''
      }
    }
  }

  post {
    always {
      archiveArtifacts allowEmptyArchive: true, artifacts: 'backend/storage/test-results/*.xml', fingerprint: true
      deleteDir()
    }
  }
}