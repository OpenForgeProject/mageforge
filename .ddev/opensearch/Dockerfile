#ddev-generated
FROM opensearchproject/opensearch:2.11.0
WORKDIR /usr/share/opensearch

RUN bin/opensearch-plugin install --batch analysis-phonetic && \
    bin/opensearch-plugin install --batch analysis-icu
