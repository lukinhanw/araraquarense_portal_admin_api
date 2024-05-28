#!/bin/bash

BASE_DIR=$(dirname "$0")
ENV_CONTENT="JWT_SECRET=CHAVE

URL_HOST=https://exmplo.com

DB_HOST=
DB_PORT=
DB_USER=
DB_PASS=
DB_DATABASE=

TOKEN_INSTAGRAM="

DIRECTORIES=("imagens" "tmp" "fotos" "fotos-noticias" "fotos-eventos" "fotos-slides" "fotos-ads")
ENV_FILE="${BASE_DIR}/.env"

function createDirs() {
  for dir in "${DIRECTORIES[@]}"; do
    path="${BASE_DIR}/${dir}"
    if [[ ! -d "${path}" ]]; then
      mkdir "${path}"
      chmod -R 777 "${path}"
      echo "Diretório ${dir} criado"
    fi
  done
}

function removeDirs() {
  for dir in "${DIRECTORIES[@]}"; do
    path="${BASE_DIR}/${dir}"
    if [[ -d "${path}" ]]; then
      rmdir "${path}"
      echo "Diretório ${dir} removido"
    fi
  done
}

function createEnvFile() {
  if [[ ! -f "${ENV_FILE}" ]]; then
    echo "${ENV_CONTENT}" > "${ENV_FILE}"
    chmod 777 "${ENV_FILE}"
    echo "Arquivo .env criado"
  fi
}

echo "Escolha a ação:"
echo "1) Instalação Nova"
echo "2) Limpar e Instalar"
read -p "Digite a opção (1/2): " ACTION

case "${ACTION}" in
  1)
    createDirs
    createEnvFile
    ;;
  2)
    removeDirs
    if [[ -f "${ENV_FILE}" ]]; then
      rm "${ENV_FILE}"
    fi
    createDirs
    createEnvFile
    ;;
  *)
    echo "Opção inválida!"
    ;;
esac