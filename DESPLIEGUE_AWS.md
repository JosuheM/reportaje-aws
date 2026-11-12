# Despliegue en AWS (capa gratuita)

Arquitectura: **una sola instancia EC2** (Amazon Linux 2023) con Apache + PHP 8.2 + MariaDB, todo en la misma máquina. Es la opción más barata: no usa RDS (que sería un costo aparte) y las imágenes/PDF que subas desde el panel **se quedan en el disco** de la instancia.

> **Costos.** Las cuentas nuevas (desde julio de 2025) eligen el *Free plan*: unos US$100–200 en créditos, válidos hasta 6 meses o hasta que se acaben; al terminar, la cuenta del plan gratuito se cierra salvo que pases a plan de pago. Elige solo instancias marcadas **"Free tier eligible"** (t3.micro / t3.small). Antes de empezar, crea una alerta en *Billing → Budgets*.
> Verifica los detalles vigentes en https://aws.amazon.com/free/

## 1. Cuenta y alerta de presupuesto

1. Crea la cuenta en https://aws.amazon.com/free/ (pide tarjeta para verificar identidad). Elige **Free plan**.
2. *Billing → Budgets → Create budget → Zero spend budget* (te avisa por correo ante cualquier cobro).
3. Región: elige una cercana, p. ej. **us-east-1** o **sa-east-1** (São Paulo). Usa siempre la misma.

## 2. Exportar tu base local (en tu PC)

Con MySQL encendido en XAMPP, en Git Bash:

```bash
/c/xampp/mysql/bin/mysqldump.exe -u root --no-tablespaces --default-character-set=utf8mb4 --single-transaction revista_digital > revista_digital.sql
```

No lo subas a git (tiene los usuarios del panel). Si no lo haces, el script carga `database/schema.sql` (base vacía).

## 3. Crear la instancia EC2

Consola → *EC2 → Launch instance*:

- **Name:** `ddp-reportaje`
- **AMI:** Amazon Linux 2023
- **Instance type:** `t3.micro` (con la etiqueta *Free tier eligible*)
- **Key pair:** crear nuevo (RSA, `.pem`). Guárdalo, no se puede volver a descargar.
- **Network → Security group:** crear uno con
  - SSH (22) → **My IP**
  - HTTP (80) → Anywhere
  - HTTPS (443) → Anywhere
- **Storage:** 20 GiB gp3 (el free plan cubre hasta 30).

Luego *EC2 → Elastic IPs → Allocate → Associate* a la instancia, para que la IP no cambie al reiniciar. (Si no la asocias, no la dejes reservada suelta: se cobra.)

## 4. Subir el SQL y entrar por SSH

En PowerShell, desde la carpeta donde está tu `.pem` y el `.sql`:

```powershell
scp -i ddp.pem revista_digital.sql ec2-user@<IP>:/tmp/revista_digital.sql
ssh -i ddp.pem ec2-user@<IP>
```

## 5. Instalar todo (un solo comando)

Dentro de la EC2:

```bash
curl -fsSL https://raw.githubusercontent.com/JosuheM/reportaje-aws/main/deploy/aws/setup-ec2.sh -o setup-ec2.sh
sudo CODIGO_RECUPERACION='un-codigo-largo-1' CODIGO_REGISTRO='un-codigo-largo-2' bash setup-ec2.sh
```

(`CODIGO_REGISTRO='-'` desactiva el registro público.) Al terminar imprime la URL: `http://<IP>/`. El panel está en `/admin/login.php`.

La contraseña de la base se genera sola y queda en `/root/ddp-credenciales.txt`.

## 6. Actualizar el sitio

Cada vez que hagas `git push` a `main`, en la EC2:

```bash
sudo bash /var/www/html/deploy/aws/actualizar.sh
```

## 7. HTTPS (opcional, necesita un dominio)

Apunta un dominio (registro A) a la Elastic IP y, en la EC2:

```bash
sudo dnf -y install python3-certbot-apache
sudo certbot --apache -d tudominio.com
```

Sin dominio, el sitio funciona solo por HTTP.

## Buenas prácticas y límites

- El puerto de MariaDB (3306) **no** está abierto en el security group; solo se accede desde la propia máquina.
- Haz copias: *EC2 → Snapshots* del volumen, o `mysqldump` periódico. Las subidas del panel solo existen en esa instancia.
- Si apagas la instancia, no pagas cómputo, pero el disco y la IP elástica siguen contando. Si borras el proyecto: *Terminate* la instancia, libera la Elastic IP y borra los snapshots.
- t3.micro tiene 1 GB de RAM; el script añade 1 GB de swap.
