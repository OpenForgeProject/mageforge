# Build Command Process

## Command and arguments

`bin/magento mageforge:themes:build themecode`

- ohne themecode
    - Liste mit möglichen themes darstellen <themecode> / <themetitle>
- mit themecode
    - Angabe meherer themecodes möglich
    `bin/magento mageforge:themes:build <theme-code> [<theme-code>...]`

- `themePath` wird anhand des `themecodes` aus der Datenbank ausgelesen
- Es wird der passende builder gesucht


## Builder

### Hyva Themes
- Support erst ab hyva > 1.2.0

#### autoRepair
- checkt ob im Themeordner `/web/tailwind` der Ordner `node_modules` existiert, ansonsten run `npm ci --quite`
- checkt ob `node_modules` aktuell ist
- gibt einen `npm outdated` output zurück (nicht verbose)
- Generate Hyvä Configuration `/bin/magento hyva:config:generate`

#### Build Prozess
- ermittelt den TailwindCSS `/web/tailwind` Ordner im Theme mit `tailwindPath`
- `npm run build`

### Magento Standard Themes
- `/node_modules/.bin/grunt clean`
- `/node_modules/.bin/grunt less`

#### autoRepair
- checkt ob im root `node_modules` existiert, wenn nicht - `npm ci`
- checkt ob grunt existiert, wenn nicht `sudo npm -i grund -g`
- checkt ob grunt-cli existiert, wenn nicht `sudo npm -i grund-cli -g`
- checkt ob `node_modules` aktuell ist, falls nein `npm ci`
- gibt einen `npm outdated` output zurück (nicht verbose)

## StaticContentDeployer
- checkt ob Magento im `developer` oder `production` mode ist
    - wenn nicht `developer` Mode
        - run `magento setup:static-content:deploy -t themecode -f`

## Cache clean
- `bin/magento cache:clean full_page block_html layout translate`
