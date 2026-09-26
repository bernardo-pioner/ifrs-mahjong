# Documentação para alunos

## Aula — banco, cadastro e usuários

- PDF para a aula: [aula/manual.pdf](aula/manual.pdf)
- Fonte PHP: [aula/manual.php](aula/manual.php)

Para ver o PHP no navegador:

```bash
php -S localhost:8082 -t documentacao/aula
```

[http://localhost:8082/manual.php](http://localhost:8082/manual.php)

## MVC

Guia de como o sistema está organizado (Model, View, Controller) e onde cada arquivo mora. Inclui o fluxo completo do dashboard (usuário → front controller → controller → model → view → usuário) para copiar nas outras funções.

- Versão para leitura e impressão: [mvc/guia-mvc.pdf](mvc/guia-mvc.pdf)
- Fonte HTML (caso precisem regenerar o PDF): [mvc/guia-mvc.html](mvc/guia-mvc.html)

Para gerar o PDF de novo, na raiz do projeto:

```bash
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
  --headless --disable-gpu --no-pdf-header-footer \
  --print-to-pdf=documentacao/mvc/guia-mvc.pdf \
  "file://$PWD/documentacao/mvc/guia-mvc.html"
```
