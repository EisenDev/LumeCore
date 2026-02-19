<?php

namespace App\Services;

use App\Models\VaultAsset;

class DeploymentGuideService
{
    /**
     * Generate a custom deployment guide based on the asset's tech stack.
     *
     * @param VaultAsset $asset
     * @return string Markdown content
     */
    public function generate(VaultAsset $asset): string
    {
        $metadata = $asset->metadata ?? [];
        $stack = $this->detectStack($metadata);
        
        $guide = "# Deployment Protocol: {$asset->file_name}\n\n";
        $guide .= "> **LUME Intelligence™ Generated Guide**\n";
        $guide .= "> Based on forensic analysis of the codebase structure.\n\n";
        
        $guide .= "## Core Technology Stack detected\n";
        foreach ($stack as $tech) {
            $guide .= "- **{$tech}**\n";
        }
        $guide .= "\n---\n\n";

        if ($this->hasTech($stack, ['Laravel', 'PHP'])) {
            $guide .= $this->getLaravelInstructions($stack);
        } elseif ($this->hasTech($stack, ['Next.js', 'React', 'Vue', 'Nuxt'])) {
            $guide .= $this->getJsInstructions($stack);
        } elseif ($this->hasTech($stack, ['Python', 'Django', 'Flask'])) {
            $guide .= $this->getPythonInstructions($stack);
        } else {
            $guide .= $this->getGenericInstructions();
        }

        $guide .= "\n## Security Hardening (LUME Recommended)\n";
        $guide .= "1. **Rotate Keys**: Immediately regenerate `APP_KEY` and API secrets.\n";
        $guide .= "2. **Database Isolation**: Ensure logic and database layers are on separate subnets if possible.\n";
        $guide .= "3. **SSL/TLS**: Force HTTPS for all connections.\n";

        return $guide;
    }

    private function detectStack(array $metadata): array
    {
        // Try to get stack from various metadata locations
        $stack = [];
        
        // 1. Synced metadata evidence
        if (isset($metadata['synced_metadata']['stack_analysis']['repo_evidence'])) {
            foreach ($metadata['synced_metadata']['stack_analysis']['repo_evidence'] as $item) {
                $stack[] = is_array($item) ? $item['name'] : $item;
            }
        }
        
        // 2. Tech assessment
        if (empty($stack) && isset($metadata['tech_assessment']['stack'])) {
            foreach ($metadata['tech_assessment']['stack'] as $item) {
                $stack[] = is_array($item) ? $item['name'] : $item;
            }
        }
        
        // 3. Fallback
        if (empty($stack) && isset($metadata['tech_stack'])) {
             foreach ($metadata['tech_stack'] as $item) {
                $stack[] = is_array($item) ? ($item['name'] ?? $item) : $item;
            }
        }

        return array_unique($stack);
    }

    private function hasTech(array $stack, array $needles): bool
    {
        foreach ($stack as $tech) {
            foreach ($needles as $needle) {
                if (stripos($tech, $needle) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    private function getLaravelInstructions(array $stack): string
    {
        $out = "## 🚀 Laravel Deployment Strategy\n\n";
        
        if ($this->hasTech($stack, ['Docker'])) {
            $out .= "### Option A: Docker (Recommended)\n";
            $out .= "Detected `Dockerfile` or `docker-compose.yml`. Use Laravel Sail or standard Docker.\n";
            $out .= "```bash\n";
            $out .= "cp .env.example .env\n";
            $out .= "./vendor/bin/sail up -d\n";
            $out .= "./vendor/bin/sail artisan migrate\n";
            $out .= "```\n\n";
        }

        $out .= "### Option B: VPS / Forge\n";
        $out .= "1. Clone repository to `/home/forge/site`.\n";
        $out .= "2. Install dependencies: `composer install --no-dev --optimize-autoloader`.\n";
        $out .= "3. Set permissions on `storage` and `bootstrap/cache`.\n";
        $out .= "4. Configure Nginx to point to `/public`.\n";
        
        return $out;
    }

    private function getJsInstructions(array $stack): string
    {
        $out = "## ⚡ Modern Web Deployment\n\n";
        
        if ($this->hasTech($stack, ['Next.js'])) {
            $out .= "### Vercel Deployment (Zero Config)\n";
            $out .= "1. Push code to GitHub.\n";
            $out .= "2. Import project in Vercel Dashboard.\n";
            $out .= "3. Helper: Detected Next.js. Vercel will auto-detect frameowork settings.\n";
            $out .= "4. Add Environment Variables from `.env.local`.\n\n";
        }

        $out .= "### Docker / Self-Hosted\n";
        $out .= "```bash\n";
        $out .= "npm install\n";
        $out .= "npm run build\n";
        $out .= "npm start\n";
        $out .= "```\n";
        $out .= "For PM2: `pm2 start npm --name 'app' -- start`\n";
        
        return $out;
    }

    private function getPythonInstructions(array $stack): string
    {
        $out = "## 🐍 Python Deployment\n\n";
        $out .= "1. Create Virtual Env: `python -m venv venv`\n";
        $out .= "2. Activate: `source venv/bin/activate`\n";
        $out .= "3. Install: `pip install -r requirements.txt`\n";
        
        if ($this->hasTech($stack, ['Django'])) {
            $out .= "4. Migrate: `python manage.py migrate`\n";
            $out .= "5. Collect Static: `python manage.py collectstatic`\n";
            $out .= "6. Run Gunicorn: `gunicorn project.wsgi:application`\n";
        }
        
        return $out;
    }

    private function getGenericInstructions(): string
    {
        return "## General Deployment\n\n1. Check `README.md` for specific build scripts.\n2. Ensure all environment variables are set.\n3. Configure build pipeline according to language standards.\n";
    }
}
