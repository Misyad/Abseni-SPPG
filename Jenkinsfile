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
          sh 'BACKEND_TEST_IMAGE="$BACKEND_TEST_IMAGE" docker compose -f infra/docker-compose.test.yml down --volumes --remove-orphans || true'
          junit allowEmptyResults: false, testResults: 'backend/storage/test-results/junit.xml'
        }
      }
    }

    stage('Frontend Checks') {
      steps {
        dir('frontend') {
          sh 'npm ci --ignore-scripts'
          sh 'npm run lint'
          sh 'npm run typecheck'
          sh 'npm audit --omit=dev --audit-level=critical'
          script {
            if (fileExists('vitest.config.ts') || fileExists('vitest.config.js') || fileExists('jest.config.ts') || fileExists('jest.config.js')) {
              sh 'npm test -- --ci'
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
        sh 'test "$(docker image inspect --format="{{ index .Config.Labels \"org.opencontainers.image.revision\" }}" "absensi-sppg-backend:$IMAGE_TAG")" = "$VCS_REF"'
        sh 'test "$(docker image inspect --format="{{ index .Config.Labels \"org.opencontainers.image.revision\" }}" "absensi-sppg-frontend:$IMAGE_TAG")" = "$VCS_REF"'
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
  }

  post {
    always {
      archiveArtifacts allowEmptyArchive: true, artifacts: 'backend/storage/test-results/*.xml', fingerprint: true
      deleteDir()
    }
  }
}