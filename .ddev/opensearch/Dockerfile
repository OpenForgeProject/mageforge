#ddev-generated
FROM opensearchproject/opensearch:latest
WORKDIR /usr/share/opensearch

RUN bin/opensearch-plugin install --batch analysis-phonetic && \
    bin/opensearch-plugin install --batch analysis-icu
