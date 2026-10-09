# Diagnóstico de desempenho da busca semântica no Solr

Data das medições: 6 de outubro de 2026.

## Resultado principal

As medições mostram que a demora da busca semântica está concentrada no componente `query` do Solr, responsável pela consulta vetorial e pela associação dos vetores filhos aos documentos pais. Facetas, highlighting e correção ortográfica representam uma parcela pequena do tempo da requisição analisada.

Consultas inéditas levaram de aproximadamente 3,5 a 10,6 segundos. Repetições ficaram mais rápidas, mas com resultados variáveis. As medições de disco mostram leituras intensas durante os testes, reforçando a hipótese de espera por dados do índice que não estão no cache de memória.

## Ambiente e tamanho do core atual

Os dados abaixo correspondem ao core `biblio`. Não foram fornecidos tamanhos ou contagens dos demais cores.

| Informação | Valor observado |
| --- | --- |
| Container | `lareferencia-solr` |
| Imagem | `apache/solr-nightly:10.1.0-SNAPSHOT` |
| Solr | `10.1.0-SNAPSHOT` |
| Lucene | `10.4.0` |
| Core | `biblio` |
| Tamanho reportado do índice | **88,03 GB** |
| Documentos ativos (`Num Docs`) | **19.693.481** |
| Documentos totais, incluindo excluídos (`Max Doc`) | **20.441.427** |
| Documentos excluídos | **747.946**, aproximadamente 3,7% do `Max Doc` |
| Segmentos | **34** |
| Diretório do índice | `/var/solr/data/biblio/index` |
| DirectoryFactory | `org.apache.solr.core.NRTCachingDirectoryFactory` |
| Processadores disponíveis à JVM | 4 |
| Arquitetura | `aarch64` |
| RAM do servidor | Aproximadamente 15 GiB |
| Heap Java | `-Xms8g -Xmx8g` |

O campo vetorial multivalorado utiliza documentos filhos internos. Portanto, a contagem de documentos do core não deve ser interpretada como quantidade de publicações nem como quantidade exata de vetores sem uma contagem específica de pais e filhos.

Configuração do campo:

```xml
<fieldType name="knn_vector"
           class="solr.DenseVectorField"
           vectorDimension="1024"
           similarityFunction="dot_product"/>

<field name="vector_multivalued"
       type="knn_vector"
       indexed="true"
       stored="false"
       multiValued="true"/>
```

Formato da consulta semântica observada:

```text
q={!parent which=$allParents score=max v=$children.q}
children.q={!knn f=vector_multivalued topK=10 filteredSearchThreshold=60 childrenOf=$allParents}[...]
allParents=*:* -_nest_path_:*
```

## Tempo por componente

A instrumentação foi feita enviando `debug=timing` ao Solr e registrando a seção `debug.timing` da resposta no log do VuFind. Os valores estão em milissegundos.

| Componente | Tempo |
| --- | ---: |
| Consulta (`query`) | **4.538 ms**, aproximadamente **99,3%** do total |
| Facetas | 16 ms |
| Highlighting | 10 ms |
| Spellcheck | 1 ms |
| Carregamento de valores de campos (`loadFieldValues`) | 5 ms |
| Total da requisição | **4.568 ms** |

Os tempos são instrumentados em etapas e subetapas; não devem ser somados indiscriminadamente, pois podem incluir medições aninhadas e arredondamentos.

O componente `query` inclui a execução da busca k-NN e da consulta de documentos pais. Este detalhamento não separa o custo do HNSW, da leitura dos vetores, da associação pai/filho e de outras operações internas da consulta.

Nesta medição, remover facetas, highlighting ou spellcheck não resolveria os segundos gastos no componente `query`.

## Consultas inéditas e repetidas

Foram executadas consultas distintas A, B e C, com uma repetição de A entre B e C.

| Consulta | Total | Componente `query` |
| --- | ---: | ---: |
| A — inédita | **3.485 ms** | **3.456 ms** |
| B — inédita | **10.561 ms** | **10.458 ms** |
| A — repetida | **848 ms** | **837 ms** |
| C — inédita | **9.520 ms** | **9.400 ms** |

A repetição de A foi aproximadamente 4,1 vezes mais rápida que sua primeira execução. Entretanto, consultas inéditas continuaram lentas mesmo após buscas anteriores.

Em outro teste, uma consulta passou de **4.568 ms** para **17 ms** quando repetida; o componente `query` caiu de **4.538 ms** para **0 ms reportados**. O zero é um valor arredondado da instrumentação, não significa ausência de trabalho.

Esses resultados são compatíveis com benefícios de cache de resultados e/ou cache de arquivos do sistema operacional. Os logs apresentados não permitem distinguir exatamente quanto do ganho veio de cada cache. A repetição de uma consulta não representa o desempenho esperado para uma consulta inédita.

## Comunicação entre VuFind e Solr

O tempo HTTP observado pelo VuFind foi muito próximo do tempo de processamento informado pelo Solr.

| Medição | Tempo HTTP | `QTime` do Solr | Diferença aproximada |
| --- | ---: | ---: | ---: |
| Primeira requisição analisada | 8.261 ms | 8.238 ms | 23 ms |
| Outra requisição semântica | 3.478 ms | 3.466 ms | 12 ms |

Essa diferença inclui custos externos ao tempo reportado pelo Solr, como transporte e tratamento da resposta; não é uma medição isolada de latência de rede. Ainda assim, os valores indicam que a comunicação não explica a demora de vários segundos nessas requisições.

Os timestamps também precisam ser comparados no mesmo fuso: os logs apresentados do VuFind usavam `-04:00`, enquanto a JVM do Solr estava configurada com `-Duser.timezone=UTC`. A linha de resumo de requisição do Solr é registrada ao término do processamento, não deve ser tratada como horário de chegada.

A geração do embedding é anterior à chamada ao Solr e constitui um custo separado. Nos exemplos fornecidos, houve geração em aproximadamente **0,136 s** e outra em **2,207 s**. Esse tempo não está incluído no `debug.timing` do Solr.

## Segunda consulta causada pelo spellcheck

O VuFind estava configurado com dois dicionários: `default` e `basicSpell`. A consulta principal usa o primeiro; o listener de spelling executa uma consulta adicional para o segundo.

Na implementação atual, essa consulta adicional chama novamente o backend semântico. Mesmo com `rows=0`, ele gera o embedding e constrói a consulta k-NN outra vez.

Em um dos exemplos:

| Requisição | Objetivo | `QTime` |
| --- | --- | ---: |
| Principal | Resultados, facetas, highlighting e dicionário `default` | 8.238 ms |
| Adicional | Sugestões do dicionário `basicSpell`, com `rows=0` | 693 ms |

Eliminar essa duplicação reduz trabalho adicional, mas não resolve por si só a lentidão da primeira busca vetorial. Também foram observadas consultas lexicais intercaladas com a semântica em outro log; sem identificadores de requisição, não é possível atribuir todas à mesma ação do usuário.

## Memória disponível

Saída de `free -h` fornecida durante o diagnóstico:

```text
               total        used        free      shared  buff/cache   available
Mem:            15Gi        10Gi       187Mi       1.0Gi       5.6Gi       4.5Gi
Swap:             0B          0B          0B
```

Memória da JVM no mesmo conjunto de informações:

| Informação | Valor |
| --- | ---: |
| Heap inicial e máximo | 8 GiB |
| Heap utilizado naquele instante | Aproximadamente 4,9 GiB |
| Heap livre naquele instante | Aproximadamente 3,1 GiB |

A memória livre dentro do heap não equivale a memória disponível para o cache de arquivos do Linux. Com `-Xms8g -Xmx8g` e `AlwaysPreTouch`, a JVM reserva e toca uma parcela importante da RAM do servidor.

Os `187 MiB` de memória livre do Linux não indicam, isoladamente, esgotamento: `available` inclui memória recuperável. Por outro lado, os `5,6 GiB` de `buff/cache` são compartilhados pelo sistema e não significam que essa quantidade inteira armazene arquivos do Solr.

A diferença de escala entre o índice de **88,03 GB** e a RAM do servidor torna plausível que consultas diferentes precisem ler partes do índice ainda fora da memória. Não é necessário que todo o índice fique em RAM, mas o conjunto de dados frequentemente acessados precisa competir por espaço de cache.

## Leituras de disco durante as buscas

Foram observadas as seguintes leituras acumuladas em `docker stats`:

| Amostra | Leitura acumulada em `BLOCK I/O` |
| --- | ---: |
| Inicial | 47,4 GB |
| Posterior | 51 GB |
| Posterior | 51,2 GB |

O usuário informou que as duas consultas no intervalo eram inéditas. O aumento total observado foi de aproximadamente **3,8 GB**, considerando os valores arredondados. Esses contadores são acumulados e não atribuem, por si só, toda a leitura às consultas; outras atividades do container precisam ser consideradas.

Durante o teste, `iostat` apresentou:

| Métrica de `nvme1n1` | Faixa observada |
| --- | ---: |
| Requisições de leitura | 982–1.017 por segundo |
| Volume lido | 109.924–115.896 KiB/s, aproximadamente 107–113 MiB/s |
| Latência média de leitura (`r_await`) | 0,93–0,95 ms |
| Requisições pendentes em média (`aqu-sz`) | 0,92–0,97 |
| Atividade do dispositivo (`%util`) | 86,3–92,6% |
| CPU em `iowait` | 21,55–23,04% |

Se `nvme1n1` armazena o índice e essas amostras correspondem às buscas, elas reforçam a hipótese de espera por leitura. A fila média próxima de uma requisição sugere pouco paralelismo nas operações observadas; não demonstra que o dispositivo atingiu sua capacidade máxima.

Em SSDs/NVMe, `%util` elevado não é prova suficiente de saturação, porque o dispositivo pode atender operações em paralelo. O fato de o nome ser `nvme1n1` também não determina se o armazenamento é local ou um volume virtualizado, nem sua capacidade provisionada.

## Normalização dos vetores

A verificação completa da tabela PostgreSQL `embeddings_dc772ff1_c0ad_491e_bed8_2b8c4cebe36f` apresentou:

| Informação | Resultado |
| --- | ---: |
| Total de vetores | **15.299.137** |
| Nulos | 0 |
| Vetores zero | 0 |
| Normalizados, com tolerância de `0,00001` | **15.299.137** |
| Não normalizados | **0** |
| Menor norma L2 | 0,9999985769314979 |
| Maior norma L2 | 1,0000013413847628 |

As diferenças em relação a 1 são compatíveis com precisão de ponto flutuante. Se esses mesmos vetores foram enviados ao Solr sem alterações, eles atendem ao requisito de `dot_product`; não há necessidade de reindexar por falta de normalização.

A quantidade de vetores no PostgreSQL não foi confirmada como a quantidade indexada no Solr. Portanto, ela não deve ser usada diretamente para dimensionar a memória do core.

## Recomendações e próximos passos

1. **Manter `dot_product`.** Os dados verificados não indicam um problema de normalização.
2. **Evitar aumentar o heap como primeira ação.** Isso pode retirar RAM do cache de arquivos sem resolver as leituras do índice.
3. **Medir o heap após GC e sob carga real.** Os 4,9 GiB usados são uma fotografia, não a quantidade mínima necessária. Se houver margem, testar 6 GiB em vez de 8 GiB pode liberar aproximadamente 2 GiB para o sistema operacional; acompanhar pausas de GC e comportamento sob concorrência.
4. **Avaliar mais RAM para ampliar o cache.** O dimensionamento depende do conjunto de arquivos vetoriais acessados, da carga e das medições; não há evidência suficiente para fixar uma quantidade exata.
5. **Confirmar o dispositivo e os limites de armazenamento.** Verificar onde o volume do índice está montado e, se for armazenamento de nuvem, suas capacidades de IOPS e throughput.
6. **Eliminar a busca vetorial extra de spellcheck.** Desabilitar spelling apenas na busca semântica ou encaminhar a consulta ortográfica a um fluxo lexical. Usar somente `basicSpell` globalmente também elimina o segundo dicionário, mas afeta buscas lexicais.
7. **Medir separadamente busca de filhos e consulta de pais.** Fazer testes controlados, preservando o vetor e registrando que a semântica dos resultados muda ao retirar o agrupamento por pai.
8. **Investigar os segmentos antes de recomendar merges.** Os 34 segmentos podem influenciar o custo, mas não justificam, isoladamente, executar `optimize`/force merge.

Após qualquer ajuste, comparar um conjunto de consultas inéditas e repetidas, registrar `QTime`, `debug.timing`, leitura de disco e GC. Após restart, permitir que o cache aqueça antes de comparar resultados. Registrar também a carga concorrente para evitar conclusões baseadas em testes com condições diferentes.

## Referências

- [Solr: parâmetros de debug e timing](https://solr.apache.org/guide/solr/latest/query-guide/common-query-parameters.html#debug-parameter).
- [Solr: dimensionamento do heap e memória para o sistema operacional](https://solr.apache.org/guide/solr/latest/deployment-guide/jvm-settings.html).
- [Solr: busca vetorial e requisitos de `dot_product`](https://solr.apache.org/guide/solr/latest/query-guide/dense-vector-search.html).
- [iostat: significado de `r_await`, `aqu-sz`, `iowait` e limites de `%util`](https://man7.org/linux/man-pages/man1/iostat.1.html).

Os números deste documento foram fornecidos nos logs e medições do servidor durante a conversa. Não foi realizado acesso direto ao servidor para confirmar os dados ou alterar sua configuração.
