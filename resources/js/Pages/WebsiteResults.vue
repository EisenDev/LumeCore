<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import * as d3 from 'd3';
import * as topojson from 'topojson-client';
import { Radar } from 'vue-chartjs';
import axios from 'axios';
import {
    Chart as ChartJS,
    RadialLinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    Legend,
} from 'chart.js';

// Import visualizers and sub-modals
import LumeAISupport from '@/Components/LumeAISupport.vue';
import TopologyGraph from '@/Components/Visualizers/TopologyGraph.vue';
import TechnologyRelationshipMap from '@/Components/Visualizers/TechnologyRelationshipMap.vue';
import TopologyDetailsModal from '@/Components/Visualizers/TopologyDetailsModal.vue';
import MetricSparkline from '@/Components/Visualizers/MetricSparkline.vue';
import PenetrationAndAQTesting from '@/Components/PenetrationAndAQTesting.vue';
import QAPenetrationResultsModal from '@/Components/QAPenetrationResultsModal.vue';
import ProjectAnalystModal from '@/Components/ProjectAnalystModal.vue';
import CreditPurchaseModal from '@/Components/CreditPurchaseModal.vue';
import UniversalScanning from '@/Components/Scanner/UniversalScanning.vue';
import {
    Monitor, Globe, Cpu, Zap, Database, Shield, HardDrive, Lock, Mail, Server
} from 'lucide-vue-next';

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

interface VaultAsset {
    id: number | string;
    user_id: number;
    file_name: string;
    file_path: string;
    file_size: number | null;
    mime_type: string | null;
    status: string;
    metadata: any;
    synced_metadata: any;
    website_metadata: any;
    repository_metadata: any;
    sync_score: number | null;
    score: number | null;
    is_for_sale: boolean;
    price: number | null;
    radar_data: any;
    created_at: string;
    updated_at: string;
}

interface Props {
    asset: VaultAsset;
    hash: string;
    history: any[];
}

const props = defineProps<Props>();

// Tabs state
const tabs = [
    { id: 'overview', label: 'Overview' },
    { id: 'forensic', label: 'Forensic Analysis' },
    { id: 'topology', label: 'Site Topology' },
    { id: 'technologies', label: 'Technologies' },
    { id: 'evidence', label: 'Evidence' },
    { id: 'recommendations', label: 'Recommendations' },
    { id: 'history', label: 'History' }
];
const activeTab = ref('overview');
const techViewMode = ref('graph');
const showEvidenceModal = ref(null);
const showFullStackModal = ref(false);

// Dialog sub-modals
const showAIDetailsModal = ref(false);
const showVectorDetailsModal = ref(false);
const showTopologyDetailsModal = ref(false);
const showAIModal = ref(false);

const showDeepScanConfirmation = ref(false);
const showQAResultsModal = ref(false);
const showProjectAnalystModal = ref(false);
const showPentestAiModal = ref(false);
const showPurchaseModal = ref(false);
const showSecurityScanningModal = ref(false);

// Map modal
const showMapModal = ref(false);
const mapThumbnailRef = ref<SVGSVGElement | null>(null);
const mapModalRef = ref<SVGSVGElement | null>(null);

// Scanning animation parameters
const animatedScore = ref(0);
const showContent = ref(false);
const hoveredTech = ref<string | null>(null);

// Dynamic Geolocation resolution
const targetDomain = computed(() => {
    let name = props.asset.file_name || '';
    name = name.replace(/^(https?:\/\/)?(www\.)?/, '');
    name = name.split('/')[0];
    return name;
});

const geoData = ref({
    ip: '104.21.32.20',
    country: 'Singapore',
    countryCode: 'SG',
    lat: 1.2921,
    lon: 103.7808,
    isp: 'Cloudflare',
    as: 'AS13335 Cloudflare, Inc.'
});

const isGeoLoading = ref(true);

const activeMapView = ref('map');
let d3Zoom: any = null;

const uniqueCountriesCount = computed(() => {
    const countries = new Set();
    countries.add(geoData.value.country);
    externalConnections.value.forEach(c => countries.add(c.label.split(' ')[0] || 'Unknown'));
    return countries.size;
});

const uniqueAsnsCount = computed(() => {
    const asns = new Set();
    asns.add(geoData.value.as.split(' ')[0]);
    externalConnections.value.forEach(c => asns.add(c.host));
    return asns.size;
});

const countryDistribution = computed(() => {
    const dist: Record<string, number> = {};
    dist[geoData.value.country] = 1;
    externalConnections.value.forEach(c => {
        let name = 'United States';
        if (c.type === 'cdn') name = 'Japan';
        else if (c.type === 'payment') name = 'Australia';
        else if (c.label.includes('Akamai') || c.label.includes('Edge')) name = 'Germany';
        dist[name] = (dist[name] || 0) + 1;
    });
    return dist;
});

const connectionTypesCount = computed(() => {
    const dist: Record<string, number> = {};
    externalConnections.value.forEach(c => {
        dist[c.type] = (dist[c.type] || 0) + 1;
    });
    return dist;
});

const formattedLastSeen = computed(() => {
    const date = props.asset.updated_at ? new Date(props.asset.updated_at) : new Date();
    return date.toLocaleString('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
});

const copyScanId = () => {
    navigator.clipboard.writeText(`SCAN-${props.asset.id.toString().toUpperCase()}`);
};

const handleZoomIn = () => {
    if (!mapModalRef.value || !d3Zoom) return;
    const svg = d3.select(mapModalRef.value);
    svg.transition().duration(250).call(d3Zoom.scaleBy as any, 1.3);
};

const handleZoomOut = () => {
    if (!mapModalRef.value || !d3Zoom) return;
    const svg = d3.select(mapModalRef.value);
    svg.transition().duration(250).call(d3Zoom.scaleBy as any, 0.7);
};

const handleZoomReset = () => {
    if (!mapModalRef.value || !d3Zoom) return;
    const svg = d3.select(mapModalRef.value);
    svg.transition().duration(250).call(d3Zoom.transform as any, d3.zoomIdentity);
};

const getMapCoords = (lat: number, lon: number) => {
    // Linear mapping calibrated to the hand-drawn SVG map
    let x = 0.53 * lon + 80;
    let y = -0.74 * lat + 63;
    // Clamp to SVG view box bounds with safety padding
    x = Math.max(10, Math.min(190, x));
    y = Math.max(10, Math.min(90, y));
    return { x, y };
};

const targetCoords = computed(() => {
    return getMapCoords(geoData.value.lat, geoData.value.lon);
});

// Map coordinates calibrated for the 760x380 equirectangular world map SVG
// Formula: x = (lon + 180) / 360 * 760,  y = (90 - lat) / 180 * 380
const geoMapX = computed(() => {
    const x = ((geoData.value.lon + 180) / 360) * 760;
    return Math.max(20, Math.min(740, x));
});
const geoMapY = computed(() => {
    const y = ((90 - geoData.value.lat) / 180) * 380;
    return Math.max(20, Math.min(360, y));
});


// External connections derived from website_metadata (third-party hosts, CDN, DB endpoints)
const externalConnections = computed(() => {
    const meta = props.asset?.website_metadata || props.asset?.metadata || {};
    const connections: Array<{ label: string; host: string; type: string; lat: number; lon: number; }> = [];

    // Known CDN/DNS providers with approximate geo coords calibrated for map.png
    const knownProviders: Record<string, { lat: number; lon: number; label: string; type: string }> = {
        'cloudflare': { lat: 35.6762, lon: 139.6503, label: 'Cloudflare CDN', type: 'cdn' }, // Tokyo, Japan
        'cloudfront': { lat: 39.0438, lon: -77.4874, label: 'AWS CloudFront', type: 'cdn' },
        'akamai': { lat: 52.5200, lon: 13.4050, label: 'Akamai Edge', type: 'cdn' }, // Berlin, Germany
        'fastly': { lat: 37.7749, lon: -122.4194, label: 'Fastly CDN', type: 'cdn' },
        'google': { lat: 37.4056, lon: -122.0775, label: 'Google Analytics', type: 'analytics' },
        'facebook': { lat: 37.484, lon: -122.1483, label: 'Facebook/Meta', type: 'social' },
        'twitter': { lat: 37.7749, lon: -122.4194, label: 'Twitter/X', type: 'social' },
        'amazonaws': { lat: 39.0438, lon: -77.4874, label: 'AWS Services', type: 'cloud' },
        'azure': { lat: 47.6062, lon: -122.3321, label: 'Microsoft Azure', type: 'cloud' },
        'stripe': { lat: -25.2744, lon: 133.7751, label: 'Stripe Payments', type: 'payment' }, // Australia
        'paypal': { lat: 37.3861, lon: -122.0839, label: 'PayPal', type: 'payment' },
        'intercom': { lat: 37.7749, lon: -122.4194, label: 'Intercom', type: 'analytics' },
        'hubspot': { lat: 42.3601, lon: -71.0589, label: 'HubSpot', type: 'crm' },
        'segment': { lat: 37.7749, lon: -122.4194, label: 'Segment Analytics', type: 'analytics' },
        'mixpanel': { lat: 37.7749, lon: -122.4194, label: 'Mixpanel', type: 'analytics' },
        'datadog': { lat: 40.7128, lon: -74.006, label: 'Datadog', type: 'monitoring' },
        'sentry': { lat: 37.7749, lon: -122.4194, label: 'Sentry', type: 'monitoring' },
    };

    // Check DNS / CDN from website_metadata
    const cdnProvider = (meta.cdn_provider || meta.cdn || '').toLowerCase();
    const dnsProvider = (meta.dns_provider || meta.dns_authority || '').toLowerCase();
    const thirdParty: string[] = meta.third_party_scripts || meta.third_party_domains || [];

    const checked = new Set<string>();
    const tryAdd = (key: string) => {
        for (const [k, v] of Object.entries(knownProviders)) {
            if (key.toLowerCase().includes(k) && !checked.has(k)) {
                checked.add(k);
                connections.push({ ...v, host: k + '.com' });
            }
        }
    };

    tryAdd(cdnProvider);
    tryAdd(dnsProvider);
    if (Array.isArray(thirdParty)) thirdParty.forEach(d => tryAdd(d));

    // Fallback/Default connection nodes to matches target mockup
    if (connections.length === 0) {
        connections.push({ label: 'Cloudflare CDN', host: 'cloudflare.com', type: 'cdn', lat: 35.6762, lon: 139.6503 });
        connections.push({ label: 'Google Analytics', host: 'google-analytics.com', type: 'analytics', lat: 37.4056, lon: -122.0775 });
        connections.push({ label: 'Stripe', host: 'stripe.com', type: 'payment', lat: -25.2744, lon: 133.7751 });
    }

    return connections;
});

// Color map for external connection types
const connectionTypeColor: Record<string, string> = {
    cdn:        '#22d3ee', // cyan
    analytics:  '#a78bfa', // purple
    social:     '#f472b6', // pink
    payment:    '#4ade80', // green
    cloud:      '#60a5fa', // blue
    crm:        '#fb923c', // orange
    monitoring: '#facc15', // yellow
};

// ============================================================
// D3 Map rendering
// ============================================================
let cachedWorldData: any = null;

async function drawD3Map(svgEl: SVGSVGElement, isThumbnail: boolean) {
    const svg = d3.select(svgEl);
    svg.selectAll('*').remove();

    const logicalWidth = 1683;
    const logicalHeight = 935;

    // Set SVG attributes for responsive scaling
    svg.attr('viewBox', `0 0 ${logicalWidth} ${logicalHeight}`)
       .attr('width', '100%')
       .attr('height', '100%')
       .attr('preserveAspectRatio', isThumbnail ? 'xMidYMid slice' : 'xMidYMid meet');

    // Create a group for zoom / pan
    const container = svg.append('g').attr('class', 'map-content-group');

    // Background container fill
    container.append('rect')
        .attr('width', logicalWidth)
        .attr('height', logicalHeight)
        .attr('fill', isThumbnail ? '#080909' : '#06070a');

    // Load TopoJSON locally and convert to GeoJSON
    let worldData = cachedWorldData;
    if (!worldData) {
        try {
            const res = await axios.get('/countries-110m.json');
            worldData = res.data;
            cachedWorldData = worldData;
        } catch (e) {
            console.error("Failed to load map TopoJSON", e);
        }
    }

    let projection = d3.geoEquirectangular()
        .center([0, 15]) // Center slightly north of the equator to balance representation
        .scale(logicalWidth / (2 * Math.PI))
        .translate([logicalWidth / 2, logicalHeight / 2]);

    if (worldData) {
        try {
            const countriesGeo = topojson.feature(worldData, worldData.objects.countries) as any;
            
            // Draw grid graticule lines
            const graticule = d3.geoGraticule();
            container.append('path')
                .datum(graticule())
                .attr('d', d3.geoPath().projection(projection) as any)
                .attr('fill', 'none')
                .attr('stroke', 'rgba(203,180,138,0.035)')
                .attr('stroke-width', 0.8);

            // Draw countries
            container.append('g')
                .attr('class', 'countries-group')
                .selectAll('path')
                .data(countriesGeo.features)
                .enter()
                .append('path')
                .attr('d', d3.geoPath().projection(projection) as any)
                .attr('fill', '#0c0e12') // premium dark fill
                .attr('stroke', 'rgba(203,180,138,0.16)') // premium gold/brown border
                .attr('stroke-width', 1)
                .on('mouseover', function() {
                    d3.select(this)
                        .transition().duration(150)
                        .attr('fill', 'rgba(203,180,138,0.06)')
                        .attr('stroke', 'rgba(203,180,138,0.3)');
                })
                .on('mouseout', function() {
                    d3.select(this)
                        .transition().duration(150)
                        .attr('fill', '#0c0e12')
                        .attr('stroke', 'rgba(203,180,138,0.16)');
                });
        } catch (e) {
            console.error("Error drawing GeoJSON paths", e);
        }
    }

    // Simple equirectangular projection mapping using the D3 projection
    const project = (lat: number, lon: number): [number, number] | null => {
        return projection([lon, lat]);
    };

    const serverLat = geoData.value.lat;
    const serverLon = geoData.value.lon;
    const serverXY = project(serverLat, serverLon);

    // Origin (client / USA): approx 39N 98W (geographic center of USA)
    const originXY = project(39, -98);

    // Draw defs for gradients (always append to svg root)
    const defs = svg.append('defs');

    // Route arc from origin → server
    if (originXY && serverXY) {
        const gradId = `routeG_${isThumbnail ? 't' : 'm'}`;
        const grad = defs.append('linearGradient').attr('id', gradId)
            .attr('x1', originXY[0]).attr('y1', originXY[1])
            .attr('x2', serverXY[0]).attr('y2', serverXY[1])
            .attr('gradientUnits', 'userSpaceOnUse');
        grad.append('stop').attr('offset', '0%').attr('stop-color', 'rgba(203,180,138,0.1)');
        grad.append('stop').attr('offset', '100%').attr('stop-color', '#CBB48A').attr('stop-opacity', 0.9);

        // Great-circle arc via SVG quadratic bezier
        const cx = (originXY[0] + serverXY[0]) / 2;
        const cy = Math.min(originXY[1], serverXY[1]) - (isThumbnail ? 150 : 250);
        const arcPath = `M${originXY[0]},${originXY[1]} Q${cx},${cy} ${serverXY[0]},${serverXY[1]}`;
        container.append('path').attr('d', arcPath)
            .attr('fill', 'none')
            .attr('stroke', `url(#${gradId})`)
            .attr('stroke-width', isThumbnail ? 8 : 4)
            .attr('stroke-dasharray', isThumbnail ? '20,15' : '12,8')
            .attr('stroke-linecap', 'round')
            .classed('route-dash-animated', true);
    }

    // Draw external connection arcs (only in modal)
    if (!isThumbnail && serverXY) {
        externalConnections.value.forEach(conn => {
            const connXY = project(conn.lat, conn.lon);
            if (!connXY) return;
            const color = connectionTypeColor[conn.type] || '#64748b';

            // Arc: server → external
            const cx2 = (serverXY[0] + connXY[0]) / 2;
            const cy2 = Math.min(serverXY[1], connXY[1]) - 80;
            container.append('path')
                .attr('d', `M${serverXY[0]},${serverXY[1]} Q${cx2},${cy2} ${connXY[0]},${connXY[1]}`)
                .attr('fill', 'none')
                .attr('stroke', color)
                .attr('stroke-width', 2)
                .attr('stroke-dasharray', '8,8')
                .attr('stroke-opacity', 0.5)
                .attr('stroke-linecap', 'round');

            // External dot
            const glowId = `extGlow_${conn.host.replace(/\W/g, '')}`;
            const f = defs.append('filter').attr('id', glowId);
            f.append('feGaussianBlur').attr('stdDeviation', 6).attr('result', 'blur');
            const fm = f.append('feMerge');
            fm.append('feMergeNode').attr('in', 'blur');
            fm.append('feMergeNode').attr('in', 'SourceGraphic');

            container.append('circle').attr('cx', connXY[0]).attr('cy', connXY[1]).attr('r', 16)
                .attr('fill', color).attr('fill-opacity', 0.12);
            container.append('circle').attr('cx', connXY[0]).attr('cy', connXY[1]).attr('r', 8)
                .attr('fill', color).attr('fill-opacity', 0.6).attr('filter', `url(#${glowId})`);
            container.append('circle').attr('cx', connXY[0]).attr('cy', connXY[1]).attr('r', 4)
                .attr('fill', color);

            // Label
            container.append('text')
                .attr('x', connXY[0] + 16).attr('y', connXY[1] - 12)
                .attr('fill', color).attr('font-size', 16)
                .attr('font-family', 'monospace').attr('font-weight', 'bold')
                .text(conn.label);
        });
    }

    // Origin dot (USA)
    if (originXY) {
        const glowId2 = `originGlow_${isThumbnail ? 't' : 'm'}`;
        const f2 = defs.append('filter').attr('id', glowId2);
        f2.append('feGaussianBlur').attr('stdDeviation', isThumbnail ? 15 : 10).attr('result', 'blur');
        const fm2 = f2.append('feMerge');
        fm2.append('feMergeNode').attr('in', 'blur');
        fm2.append('feMergeNode').attr('in', 'SourceGraphic');

        container.append('circle').attr('cx', originXY[0]).attr('cy', originXY[1])
            .attr('r', isThumbnail ? 30 : 20).attr('fill', '#CBB48A').attr('fill-opacity', 0.12);
        container.append('circle').attr('cx', originXY[0]).attr('cy', originXY[1])
            .attr('r', isThumbnail ? 15 : 10).attr('fill', '#CBB48A').attr('fill-opacity', 0.6)
            .attr('filter', `url(#${glowId2})`);
        container.append('circle').attr('cx', originXY[0]).attr('cy', originXY[1])
            .attr('r', isThumbnail ? 7 : 5).attr('fill', '#CBB48A');
    }

    // Server target pin
    if (serverXY) {
        const glowId3 = `serverGlow_${isThumbnail ? 't' : 'm'}`;
        const f3 = defs.append('filter').attr('id', glowId3);
        f3.append('feGaussianBlur').attr('stdDeviation', isThumbnail ? 20 : 15).attr('result', 'blur');
        const fm3 = f3.append('feMerge');
        fm3.append('feMergeNode').attr('in', 'blur');
        fm3.append('feMergeNode').attr('in', 'SourceGraphic');

        // Pulsing animation using SVG SMIL animate tags
        const pulseOuter = container.append('circle')
            .attr('cx', serverXY[0])
            .attr('cy', serverXY[1])
            .attr('fill', '#CBB48A');

        pulseOuter.append('animate')
            .attr('attributeName', 'r')
            .attr('values', `${isThumbnail ? 30 : 20}; ${isThumbnail ? 75 : 50}; ${isThumbnail ? 100 : 70}`)
            .attr('dur', '2.2s')
            .attr('repeatCount', 'indefinite');

        pulseOuter.append('animate')
            .attr('attributeName', 'fill-opacity')
            .attr('values', '0.15; 0.04; 0')
            .attr('dur', '2.2s')
            .attr('repeatCount', 'indefinite');

        const pulseInner = container.append('circle')
            .attr('cx', serverXY[0])
            .attr('cy', serverXY[1])
            .attr('fill', '#CBB48A');

        pulseInner.append('animate')
            .attr('attributeName', 'r')
            .attr('values', `${isThumbnail ? 18 : 12}; ${isThumbnail ? 45 : 30}; ${isThumbnail ? 60 : 42}`)
            .attr('dur', '2.2s')
            .attr('begin', '0.5s')
            .attr('repeatCount', 'indefinite');

        pulseInner.append('animate')
            .attr('attributeName', 'fill-opacity')
            .attr('values', '0.2; 0.08; 0')
            .attr('dur', '2.2s')
            .attr('begin', '0.5s')
            .attr('repeatCount', 'indefinite');

        container.append('circle').attr('cx', serverXY[0]).attr('cy', serverXY[1])
            .attr('r', isThumbnail ? 18 : 12).attr('fill', '#CBB48A').attr('fill-opacity', 0.5)
            .attr('filter', `url(#${glowId3})`);
        container.append('circle').attr('cx', serverXY[0]).attr('cy', serverXY[1])
            .attr('r', isThumbnail ? 9 : 6).attr('fill', '#CBB48A');

        if (!isThumbnail) {
            // Label for server
            container.append('text')
                .attr('x', serverXY[0] + 18).attr('y', serverXY[1] - 15)
                .attr('fill', '#CBB48A').attr('font-size', 20)
                .attr('font-family', 'monospace').attr('font-weight', 'bold')
                .text(`${geoData.value.country} (${geoData.value.countryCode})`);
            container.append('text')
                .attr('x', serverXY[0] + 18).attr('y', serverXY[1] + 8)
                .attr('fill', 'rgba(203,180,138,0.6)').attr('font-size', 16)
                .attr('font-family', 'monospace')
                .text(`IP: ${geoData.value.ip} · ${geoData.value.isp}`);
        }
    }

    // Set up D3 Zoom if not thumbnail
    if (!isThumbnail) {
        d3Zoom = d3.zoom()
            .scaleExtent([1, 8])
            .on('zoom', (event) => {
                container.attr('transform', event.transform);
            });
        svg.call(d3Zoom);
    }
}

const fetchGeoLocation = async () => {
    try {
        isGeoLoading.value = true;
        const res = await axios.get(`https://ip-api.com/json/${targetDomain.value}`);
        if (res.data && res.data.status === 'success') {
            geoData.value = {
                ip: res.data.query || '104.21.32.20',
                country: res.data.country || 'Singapore',
                countryCode: res.data.countryCode || 'SG',
                lat: typeof res.data.lat === 'number' ? res.data.lat : 1.2921,
                lon: typeof res.data.lon === 'number' ? res.data.lon : 103.7808,
                isp: res.data.isp || 'Cloudflare',
                as: res.data.as || 'AS13335 Cloudflare, Inc.'
            };
        }
    } catch (e) {
        console.error('Error fetching geolocation:', e);
    } finally {
        isGeoLoading.value = false;
    }
};

// Evidence Page filter and search
const activeEvidenceTab = ref('all'); // all, network, http, code, config, file, other
const evidenceSearchQuery = ref('');

interface EvidenceItem {
    id: number | string;
    name: string;
    value: string;
    type: string;
    category: string;
    time: string;
    status: 'success' | 'warning' | 'error';
}

const evidenceList = ref<EvidenceItem[]>([
    // HTTP (18 items)
    { id: 1, name: 'admin/login.php', value: 'HTTP 200', type: 'Web Response', category: 'http', time: 'Jun 22, 2026 16:54:21', status: 'success' },
    { id: 2, name: '/api/v1/contact', value: 'HTTP 200', type: 'Web Response', category: 'http', time: 'Jun 22, 2026 16:54:18', status: 'success' },
    { id: 3, name: '/wp-json/wp/v2/', value: 'HTTP 200', type: 'Web Response', category: 'http', time: 'Jun 22, 2026 16:54:16', status: 'success' },
    { id: 4, name: '/.env.example', value: 'HTTP 200', type: 'Web Response', category: 'http', time: 'Jun 22, 2026 16:54:13', status: 'success' },
    { id: 5, name: '/server-status', value: 'HTTP 403', type: 'Web Response', category: 'http', time: 'Jun 22, 2026 16:54:10', status: 'error' },
    { id: 6, name: '/xmlrpc.php', value: 'HTTP 405', type: 'Web Response', category: 'http', time: 'Jun 22, 2026 16:54:05', status: 'warning' },
    { id: 7, name: '/wp-admin/', value: 'HTTP 302', type: 'Redirect', category: 'http', time: 'Jun 22, 2026 16:54:01', status: 'success' },
    { id: 8, name: '/wp-login.php', value: 'HTTP 200', type: 'Web Response', category: 'http', time: 'Jun 22, 2026 16:53:58', status: 'success' },
    { id: 9, name: '/feed/', value: 'HTTP 200', type: 'XML Feed', category: 'http', time: 'Jun 22, 2026 16:53:55', status: 'success' },
    { id: 10, name: '/sitemap.xml', value: 'HTTP 200', type: 'XML Sitemap', category: 'http', time: 'Jun 22, 2026 16:53:50', status: 'success' },
    { id: 11, name: '/robots.txt', value: 'HTTP 200', type: 'Plain Text', category: 'http', time: 'Jun 22, 2026 16:53:45', status: 'success' },
    { id: 12, name: '/favicon.ico', value: 'HTTP 200', type: 'Image', category: 'http', time: 'Jun 22, 2026 16:53:40', status: 'success' },
    { id: 13, name: '/assets/index.js', value: 'HTTP 200', type: 'JavaScript', category: 'http', time: 'Jun 22, 2026 16:53:35', status: 'success' },
    { id: 14, name: '/assets/index.css', value: 'HTTP 200', type: 'CSS', category: 'http', time: 'Jun 22, 2026 16:53:30', status: 'success' },
    { id: 15, name: '/api/v1/status', value: 'HTTP 200', type: 'JSON', category: 'http', time: 'Jun 22, 2026 16:53:25', status: 'success' },
    { id: 16, name: '/api/v1/health', value: 'HTTP 200', type: 'JSON', category: 'http', time: 'Jun 22, 2026 16:53:20', status: 'success' },
    { id: 17, name: '/non-existent-page', value: 'HTTP 404', type: 'Web Response', category: 'http', time: 'Jun 22, 2026 16:53:15', status: 'warning' },
    { id: 18, name: '/wp-content/uploads/', value: 'HTTP 403', type: 'Directory Listing', category: 'http', time: 'Jun 22, 2026 16:53:10', status: 'warning' },

    // Network (14 items)
    { id: 19, name: 'DNS A Record', value: '103.21.244.0', type: 'DNS', category: 'network', time: 'Jun 22, 2026 16:53:41', status: 'success' },
    { id: 20, name: 'DNS MX Record', value: 'mail.infosoft.poolreno.com', type: 'DNS', category: 'network', time: 'Jun 22, 2026 16:53:41', status: 'success' },
    { id: 21, name: 'Open Port 443 (HTTPS)', value: 'TCP Open', type: 'Network', category: 'network', time: 'Jun 22, 2026 16:53:21', status: 'success' },
    { id: 22, name: 'Open Port 80 (HTTP)', value: 'TCP Open', type: 'Network', category: 'network', time: 'Jun 22, 2026 16:53:20', status: 'success' },
    { id: 23, name: 'TLS Certificate', value: '*.infosoft.poolreno.com', type: 'TLS', category: 'network', time: 'Jun 22, 2026 16:53:19', status: 'success' },
    { id: 24, name: 'DNS TXT Record', value: 'v=spf1 include:spf.protection.outlook.com -all', type: 'DNS', category: 'network', time: 'Jun 22, 2026 16:53:15', status: 'success' },
    { id: 25, name: 'DNS CNAME Record', value: 'autodiscover.outlook.com', type: 'DNS', category: 'network', time: 'Jun 22, 2026 16:53:10', status: 'success' },
    { id: 26, name: 'DNS NS Record', value: 'ns1.livedns.co.uk', type: 'DNS', category: 'network', time: 'Jun 22, 2026 16:53:05', status: 'success' },
    { id: 27, name: 'Open Port 22 (SSH)', value: 'TCP Closed', type: 'Network', category: 'network', time: 'Jun 22, 2026 16:53:00', status: 'success' },
    { id: 28, name: 'Open Port 21 (FTP)', value: 'TCP Closed', type: 'Network', category: 'network', time: 'Jun 22, 2026 16:52:55', status: 'success' },
    { id: 29, name: 'Open Port 3306 (MySQL)', value: 'TCP Blocked', type: 'Network', category: 'network', time: 'Jun 22, 2026 16:52:50', status: 'success' },
    { id: 30, name: 'Open Port 8080 (HTTP-Alt)', value: 'TCP Closed', type: 'Network', category: 'network', time: 'Jun 22, 2026 16:52:45', status: 'success' },
    { id: 31, name: 'SSL Cipher Strength', value: 'TLS 1.3 Strong Ciphers', type: 'TLS', category: 'network', time: 'Jun 22, 2026 16:52:40', status: 'success' },
    { id: 32, name: 'IPv6 Address Record', value: '2606:4700:3030::6815:2014', type: 'DNS', category: 'network', time: 'Jun 22, 2026 16:52:35', status: 'success' },

    // Code (12 items)
    { id: 33, name: 'package.json', value: 'Discovered', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:55', status: 'success' },
    { id: 34, name: 'composer.json', value: 'Discovered', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:54', status: 'success' },
    { id: 35, name: '.git/config', value: 'Discovered', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:51', status: 'success' },
    { id: 36, name: 'webpack.config.js', value: 'Discovered', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:49', status: 'success' },
    { id: 37, name: 'robots.txt', value: 'Discovered', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:45', status: 'success' },
    { id: 38, name: 'index.php', value: 'Entry Point', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:40', status: 'success' },
    { id: 39, name: '.env', value: 'Missing (Verified Secure)', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:35', status: 'success' },
    { id: 40, name: '.gitignore', value: 'Discovered', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:30', status: 'success' },
    { id: 41, name: 'artisan', value: 'Laravel Executable', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:25', status: 'success' },
    { id: 42, name: 'tailwind.config.js', value: 'CSS Configuration', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:20', status: 'success' },
    { id: 43, name: 'vite.config.js', value: 'Vite Configuration', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:15', status: 'success' },
    { id: 44, name: 'app/Http/Kernel.php', value: 'Middleware Configuration', type: 'File', category: 'code', time: 'Jun 22, 2026 16:52:10', status: 'success' },

    // Configuration (10 items)
    { id: 45, name: 'Nginx Conf', value: 'worker_connections 1024', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:52:05', status: 'success' },
    { id: 46, name: 'PHP ini', value: 'expose_php = Off', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:52:00', status: 'success' },
    { id: 47, name: 'PHP ini', value: 'display_errors = Off', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:51:55', status: 'success' },
    { id: 48, name: 'Security Headers', value: 'X-Frame-Options: SAMEORIGIN', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:51:50', status: 'success' },
    { id: 49, name: 'Security Headers', value: 'X-Content-Type-Options: nosniff', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:51:45', status: 'success' },
    { id: 50, name: 'SSL Protocols', value: 'TLSv1.2 TLSv1.3 Enabled', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:51:40', status: 'success' },
    { id: 51, name: 'Gzip Compression', value: 'Enabled', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:51:35', status: 'success' },
    { id: 52, name: 'CORS Policy', value: 'Access-Control-Allow-Origin: Restricted', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:51:30', status: 'success' },
    { id: 53, name: 'Session Cookies', value: 'Secure & HttpOnly Flags', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:51:25', status: 'success' },
    { id: 54, name: 'HTTP Strict Transport', value: 'HSTS Not Configured', type: 'Config', category: 'config', time: 'Jun 22, 2026 16:51:20', status: 'warning' },

    // File & Data (6 items)
    { id: 55, name: 'sitemap.xml', value: 'Valid XML Format', type: 'File', category: 'file', time: 'Jun 22, 2026 16:51:15', status: 'success' },
    { id: 56, name: 'robots.txt', value: 'Allow: /', type: 'File', category: 'file', time: 'Jun 22, 2026 16:51:10', status: 'success' },
    { id: 57, name: 'uploads/.htaccess', value: 'Access Denied (Secure)', type: 'File', category: 'file', time: 'Jun 22, 2026 16:51:05', status: 'success' },
    { id: 58, name: 'storage/logs/laravel.log', value: 'Direct Access Prevented', type: 'File', category: 'file', time: 'Jun 22, 2026 16:51:00', status: 'success' },
    { id: 59, name: 'public/assets/manifest.json', value: 'Asset Mapping File', type: 'File', category: 'file', time: 'Jun 22, 2026 16:50:55', status: 'success' },
    { id: 60, name: 'database/schema/mysql-schema.sql', value: 'Missing (Verified Secure)', type: 'File', category: 'file', time: 'Jun 22, 2026 16:50:50', status: 'success' },

    // Other (4 items)
    { id: 61, name: 'Cookie: PHPSESSID', value: 'Session Identifier', type: 'Cookie', category: 'other', time: 'Jun 22, 2026 16:50:45', status: 'success' },
    { id: 62, name: 'Cookie: XSRF-TOKEN', value: 'CSRF Protection', type: 'Cookie', category: 'other', time: 'Jun 22, 2026 16:50:40', status: 'success' },
    { id: 63, name: 'Server Header', value: 'nginx (Version Hidden)', type: 'Header', category: 'other', time: 'Jun 22, 2026 16:50:35', status: 'success' },
    { id: 64, name: 'HTTP Protocol', value: 'HTTP/2 Protocol Enabled', type: 'Network', category: 'other', time: 'Jun 22, 2026 16:50:30', status: 'success' }
]);

const filteredEvidence = computed(() => {
    const query = evidenceSearchQuery.value.toLowerCase().trim();
    return evidenceList.value.filter(item => {
        const matchesCategory = activeEvidenceTab.value === 'all' || item.category === activeEvidenceTab.value;
        const matchesQuery = !query || 
            item.name.toLowerCase().includes(query) ||
            item.value.toLowerCase().includes(query) ||
            item.type.toLowerCase().includes(query);
        return matchesCategory && matchesQuery;
    });
});

const evidenceModalSearchQuery = ref('');

const filteredModalEvidence = computed(() => {
    const query = evidenceModalSearchQuery.value.toLowerCase().trim();
    let items: any[] = [];
    
    if (!showEvidenceModal.value) return [];
    const modalType = String(showEvidenceModal.value).toLowerCase();
    
    if (modalType === 'http') {
        items = evidenceList.value.filter(item => item.category === 'http');
    } else if (modalType === 'network') {
        items = evidenceList.value.filter(item => item.category === 'network');
    } else if (modalType === 'code') {
        items = evidenceList.value.filter(item => item.category === 'code');
    } else if (modalType === 'dns' || modalType === 'dns history') {
        items = evidenceList.value.filter(item => item.category === 'network' && (item.type === 'DNS' || item.name.includes('DNS')));
    } else if (modalType === 'fingerprints') {
        items = [
            { id: 'fp1', name: 'Laravel', value: '11.x', type: 'Backend', status: 'success', category: 'fingerprints', time: 'Jun 22, 2026' },
            { id: 'fp2', name: 'WordPress', value: '6.4.x', type: 'CMS', status: 'success', category: 'fingerprints', time: 'Jun 22, 2026' },
            { id: 'fp3', name: 'Vue.js', value: '3.3.x', type: 'Frontend', status: 'success', category: 'fingerprints', time: 'Jun 22, 2026' },
            { id: 'fp4', name: 'Tailwind CSS', value: '3.x', type: 'CSS Framework', status: 'success', category: 'fingerprints', time: 'Jun 22, 2026' },
            { id: 'fp5', name: 'PHP', value: '8.2.x', type: 'Language', status: 'success', category: 'fingerprints', time: 'Jun 22, 2026' },
        ];
    } else {
        items = evidenceList.value;
    }
    
    if (query) {
        return items.filter(item => 
            item.name.toLowerCase().includes(query) || 
            item.value.toLowerCase().includes(query) ||
            item.type.toLowerCase().includes(query)
        );
    }
    return items;
});

watch(showEvidenceModal, () => {
    evidenceModalSearchQuery.value = '';
});

const resetEvidenceFilters = () => {
    activeEvidenceTab.value = 'all';
    evidenceSearchQuery.value = '';
};

// Recommendations Page filter
const activeRecFilter = ref('all'); // all, critical, high, medium, low

interface RecommendationItem {
    id: number;
    title: string;
    description: string;
    why: string;
    severity: 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';
    impact: number;
    difficulty: 'Low' | 'Medium' | 'High';
    time: string;
}

const recommendationsList = ref<RecommendationItem[]>([
    {
        id: 1,
        title: 'Restrict Administrative Access',
        description: 'Limit access to admin endpoints (/admin, /login, /wp-admin) using IP allowlists, VPN, or authentication gateways. Exposure increases risk of unauthorized access.',
        why: 'Exposed administrative endpoints are the primary target for credential stuffing and brute-force attacks.',
        severity: 'CRITICAL',
        impact: 12,
        difficulty: 'Low',
        time: '15 min'
    },
    {
        id: 2,
        title: 'Apply Security Headers',
        description: 'Implement HSTS, X-Frame-Options, X-Content-Type-Options, and CSP headers to mitigate common web vulnerabilities and clickjacking attacks.',
        why: 'HTTP security headers provide a crucial layer of defense-in-depth, preventing modern browsers from running malicious scripts.',
        severity: 'HIGH',
        impact: 8,
        difficulty: 'Medium',
        time: '30 min'
    },
    {
        id: 3,
        title: 'Update Outdated Frameworks',
        description: 'Laravel 11.x and other outdated components contain known vulnerabilities. Keep all frameworks and dependencies up to date.',
        why: 'Running outdated packages exposes the application to publicly known CVEs with automated exploits available.',
        severity: 'HIGH',
        impact: 10,
        difficulty: 'Medium',
        time: '45 min'
    },
    {
        id: 4,
        title: 'Enforce Strong Authentication',
        description: 'Implement MFA for all administrator accounts and enforce strong password policies to prevent brute-force and credential stuffing attacks.',
        why: 'Weak or reused credentials can bypass other security layers entirely without triggering alerts.',
        severity: 'MEDIUM',
        impact: 6,
        difficulty: 'Low',
        time: '20 min'
    },
    {
        id: 5,
        title: 'Optimize Third-Party Scripts',
        description: 'Review and remove unnecessary third-party scripts to reduce attack surface, improve performance, and protect user privacy.',
        why: 'Every external JS file loaded directly by users can be a potential entry point if their CDN is compromised.',
        severity: 'LOW',
        impact: 3,
        difficulty: 'Low',
        time: '30 min'
    },
    {
        id: 6,
        title: 'Enable SQL Injection Protection',
        description: 'Use parameterized queries and ORM features universally to neutralize database injection risks on public search forms.',
        why: 'SQL injection allows attackers to extract entire databases, modify data, or potentially execute OS commands.',
        severity: 'CRITICAL',
        impact: 15,
        difficulty: 'Medium',
        time: '25 min'
    },
    {
        id: 7,
        title: 'Setup Automated Backups',
        description: 'Establish encrypted off-site daily backups for all customer databases and assets with a tested recovery playbook.',
        why: 'Data loss due to hardware failure or ransomware can be fatal to operational continuity without tested backups.',
        severity: 'MEDIUM',
        impact: 5,
        difficulty: 'Low',
        time: '15 min'
    },
    {
        id: 8,
        title: 'Enforce HTTPS Redirects',
        description: 'Redirect all port 80 traffic to 443 at the Nginx level and set the \'Secure\' attribute on all session cookies.',
        why: 'Unencrypted connections allow local network eavesdroppers to intercept session tokens and sensitive client data.',
        severity: 'HIGH',
        impact: 7,
        difficulty: 'Low',
        time: '10 min'
    },
    {
        id: 9,
        title: 'Disable Directory Indexing',
        description: 'Ensure directory listing is disabled in web server configs to prevent discovery of backup zip files or source code.',
        why: 'Directory indexing exposes the file structure, making it trivial for attackers to locate hidden assets and configuration files.',
        severity: 'LOW',
        impact: 2,
        difficulty: 'Low',
        time: '5 min'
    },
    {
        id: 10,
        title: 'Setup Rate Limiting',
        description: 'Apply rate limits to API endpoints and login forms to prevent automated brute-force attacks and resource exhaustion.',
        why: 'Without rate limiting, malicious scripts can spam resource-intensive endpoints and cause denial of service.',
        severity: 'MEDIUM',
        impact: 6,
        difficulty: 'Medium',
        time: '20 min'
    },
    {
        id: 11,
        title: 'Secure Env File Access',
        description: 'Configure web server rules to return a 403 Forbidden for any requests to .env, .git, or backup files in the public directory.',
        why: 'Environment files contain database passwords, API keys, and encryption secrets. Exposure compromises the entire system.',
        severity: 'CRITICAL',
        impact: 14,
        difficulty: 'Low',
        time: '10 min'
    },
    {
        id: 12,
        title: 'Configure Content Security Policy (CSP)',
        description: 'Deploy a strict Content Security Policy (CSP) header restricting scripts and styles to trusted origins to mitigate Cross-Site Scripting (XSS).',
        why: 'CSP is the most effective browser-level mitigation against execution of unauthorized scripts.',
        severity: 'HIGH',
        impact: 9,
        difficulty: 'High',
        time: '60 min'
    }
]);

const expandedRecommendations = ref<Record<number, boolean>>({});
const recShowAll = ref(false);

const toggleRecommendation = (id: number) => {
    expandedRecommendations.value[id] = !expandedRecommendations.value[id];
};

const filteredRecommendations = computed(() => {
    if (activeRecFilter.value === 'all') {
        return recommendationsList.value;
    }
    return recommendationsList.value.filter(
        item => item.severity.toLowerCase() === activeRecFilter.value.toLowerCase()
    );
});

const difficultyClass = (difficulty: string, dotIndex: number) => {
    const diff = difficulty.toLowerCase();
    if (diff === 'low') {
        return dotIndex === 1 ? 'bg-emerald-500' : 'bg-slate-700';
    } else if (diff === 'medium') {
        return dotIndex <= 2 ? 'bg-yellow-500' : 'bg-slate-700';
    } else {
        return 'bg-rose-500';
    }
};

const auditProgress = ref<any>({
    step: 'Idle',
    progress: 0,
    details: ''
});

// Format label utilities
const formatLabel = (label: string): string => {
    if (!label) return '';
    return label.replace(/[_-]/g, ' ').replace(/[#@$%^&*()]/g, '').trim().toUpperCase();
};

function animateScoreTo(targetScore: number) {
    const duration = 1200;
    const startTime = performance.now();
    const startScore = animatedScore.value;
    
    function tick(currentTime: number) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        animatedScore.value = Math.round(startScore + (targetScore - startScore) * easeOut);
        if (progress < 1) {
            requestAnimationFrame(tick);
        }
    }
    requestAnimationFrame(tick);
}

async function openMapModal() {
    showMapModal.value = true;
    await nextTick();
    if (mapModalRef.value) {
        const el = mapModalRef.value as SVGSVGElement;
        await drawD3Map(el, false);
    }
}

onMounted(async () => {
    showContent.value = true;
    animateScoreTo(props.asset.score || 32);

    // Draw initial thumbnail map immediately using default/fallback geoData (Singapore)
    await nextTick();
    if (mapThumbnailRef.value) {
        await drawD3Map(mapThumbnailRef.value as SVGSVGElement, true);
    }

    // Resolve real geo data in background
    fetchGeoLocation();

    // Setup Echo listener if active scan
    const page = usePage();
    const user = page.props.auth.user;
    if (user?.id && (window as any).Echo) {
        (window as any).Echo.private(`user.${user.id}`)
            .listen('.scan.progress', (e: any) => {
                if (e.step) auditProgress.value.step = e.step;
                if (e.progress !== undefined) auditProgress.value.progress = e.progress;
                if (e.details) auditProgress.value.details = e.details;
            });
    }
});

// Redraw thumbnail when geo data is updated
watch(geoData, async () => {
    await nextTick();
    if (mapThumbnailRef.value) {
        await drawD3Map(mapThumbnailRef.value as SVGSVGElement, true);
    }
}, { deep: true });

// Redraw thumbnail when forensic tab becomes active to solve mount rendering lifecycle issue
watch(activeTab, async (newTab) => {
    if (newTab === 'forensic') {
        await nextTick();
        if (mapThumbnailRef.value) {
            await drawD3Map(mapThumbnailRef.value as SVGSVGElement, true);
        }
    }
});

// Extracts metadata fields safely
const auditData = computed(() => {
    return props.asset.metadata || {};
});

// Mocked / formatted sparkline history
const historyData = computed(() => {
    if (props.history && props.history.length > 0) {
        return props.history.map(h => h.score || 0).reverse();
    }
    return [40, 50, 45, 60, 55, props.asset.score || 32];
});

// Counts risks breakdown
const riskBreakdown = computed(() => {
    const meta = auditData.value;
    const counts = { crit: 8, high: 11, med: 7, low: 4, info: 2, total: 32 };
    
    if (meta.findings_breakdown) {
        counts.crit = meta.findings_breakdown.critical ?? 8;
        counts.high = meta.findings_breakdown.high ?? 11;
        counts.med = meta.findings_breakdown.medium ?? 7;
        counts.low = meta.findings_breakdown.low ?? 4;
        counts.info = meta.findings_breakdown.info ?? 2;
    }
    counts.total = counts.crit + counts.high + counts.med + counts.low + counts.info;
    return counts;
});

// Retrieves technologies stack list
const techStack = computed(() => {
    const meta = auditData.value;
    if (meta.tech_stack && Array.isArray(meta.tech_stack)) {
        return meta.tech_stack;
    }
    // Default fallback technologies for infosoft mockup
    return [
        { name: 'Laravel 11.x', category: 'Backend', dot_color: '#ef4444' },
        { name: 'WordPress 6.4.x', category: 'CMS', dot_color: '#3b82f6' },
        { name: 'Vue.js 3.x', category: 'Frontend', dot_color: '#10b981' },
        { name: 'Tailwind CSS 3.x', category: 'CSS Framework', dot_color: '#06b6d4' },
        { name: 'Cloudflare', category: 'CDN / Proxy', dot_color: '#f97316' },
        { name: 'MySQL 8.0', category: 'Database', dot_color: '#3b82f6' },
        { name: 'Nginx', category: 'Web Server', dot_color: '#10b981' },
        { name: 'Google Analytics', category: 'Analytics', dot_color: '#f59e0b' }
    ];
});

// Category resolver
function getTechCategory(name: string): string {
    const n = name.toLowerCase();
    if (n.includes('laravel') || n.includes('php') || n.includes('node') || n.includes('python')) return 'Backend';
    if (n.includes('vue') || n.includes('react') || n.includes('js') || n.includes('ts')) return 'Frontend';
    if (n.includes('tailwind') || n.includes('css')) return 'CSS Framework';
    if (n.includes('cloudflare')) return 'CDN / Security';
    if (n.includes('mysql') || n.includes('postgres') || n.includes('database')) return 'Database';
    if (n.includes('nginx') || n.includes('apache')) return 'Server';
    if (n.includes('wordpress') || n.includes('cms')) return 'CMS';
    return 'Library';
}

function getTechBadgeColor(name: string): string {
    const cat = getTechCategory(name);
    if (cat === 'Backend') return 'bg-rose-500/10 border-rose-500/20 text-rose-400';
    if (cat === 'Frontend') return 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400';
    if (cat === 'CSS Framework') return 'bg-cyan-500/10 border-cyan-500/20 text-cyan-400';
    if (cat === 'CDN / Security') return 'bg-orange-500/10 border-orange-500/20 text-orange-400';
    if (cat === 'Database') return 'bg-blue-500/10 border-blue-500/20 text-blue-400';
    return 'bg-slate-500/10 border-slate-500/20 text-slate-400';
}

function getTechExplanation(name: string): string {
    const n = name.toLowerCase();
    if (n.includes('laravel')) return 'Laravel is an open-source PHP framework utilized for robust backend application design.';
    if (n.includes('wordpress')) return 'WordPress is a PHP-based content management system used for site layouts.';
    if (n.includes('vue')) return 'Vue.js is an open-source model-view-viewmodel front-end JavaScript framework.';
    if (n.includes('tailwind')) return 'Tailwind CSS is an open-source utility-first CSS framework for custom markup layouts.';
    return `Detected ${name} signature running on target host headers and composition logs.`;
}

// Retrieves radar coordinates
const radarData = computed(() => {
    return props.asset.radar_data || auditData.value.hexagon_vectors || {
        infrastructure_maturity: 80,
        security_perimeter: 75,
        database_architecture: 60,
        supply_chain_governance: 70,
        code_efficiency: 65
    };
});

// Custom SVG Radar Chart Calculation Helpers
const radarValues = computed(() => [
    radarData.value.infrastructure_maturity ?? 80,
    radarData.value.security_perimeter ?? 75,
    radarData.value.database_architecture ?? 60,
    radarData.value.supply_chain_governance ?? 70,
    radarData.value.code_efficiency ?? 65
]);

const angleStep = (2 * Math.PI) / 5;
const cx = 190;
const cy = 140;
const rMax = 105;

const getCoords = (val: number, index: number) => {
    const angle = -Math.PI / 2 + index * angleStep;
    const dist = (val / 100) * rMax;
    return {
        x: cx + dist * Math.cos(angle),
        y: cy + dist * Math.sin(angle)
    };
};

const getAxisCoords = (index: number) => {
    const angle = -Math.PI / 2 + index * angleStep;
    return {
        x: cx + rMax * Math.cos(angle),
        y: cy + rMax * Math.sin(angle)
    };
};

const polygonPoints = computed(() => {
    return radarValues.value.map((val, idx) => {
        const coords = getCoords(val, idx);
        return `${coords.x.toFixed(2)},${coords.y.toFixed(2)}`;
    }).join(' ');
});

// Radar Chart Config
const chartData = computed(() => {
    const values = [
        radarData.value.infrastructure_maturity ?? 80,
        radarData.value.security_perimeter ?? 75,
        radarData.value.database_architecture ?? 60,
        radarData.value.supply_chain_governance ?? 70,
        radarData.value.code_efficiency ?? 65
    ];
    return {
        labels: [
            ['Architecture', `${values[0]}/100`],
            ['Access Control', `${values[1]}/100`],
            ['Data Exposure', `${values[2]}/100`],
            ['Security Posture', `${values[3]}/100`],
            ['Third-Party', `${values[4]}/100`]
        ],
        datasets: [{
            label: 'Security Level',
            data: values,
            backgroundColor: 'rgba(203, 180, 138, 0.12)',
            borderColor: '#CBB48A',
            borderWidth: 1.5,
            pointBackgroundColor: '#CBB48A',
            pointBorderColor: '#070709',
            pointBorderWidth: 1,
            pointRadius: 3,
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#ef4444',
        }]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        r: {
            beginAtZero: true,
            max: 100,
            ticks: { stepSize: 20, display: false },
            grid: { color: 'rgba(203, 180, 138, 0.08)' },
            angleLines: { color: 'rgba(203, 180, 138, 0.08)' },
            pointLabels: { color: '#94a3b8', font: { size: 8.5, family: 'monospace', weight: 'bold' }, centerPointLabels: true },
        }
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgba(10, 10, 12, 0.95)',
            titleColor: '#fff',
            bodyColor: '#94a3b8',
            borderColor: 'rgba(255, 255, 255, 0.08)',
            borderWidth: 1,
            padding: 10,
            cornerRadius: 8
        }
    }
};

// Site Topology Data Mapper
const topologyData = computed(() => {
    const rootName = props.asset.file_name;
    const nodes = [
        { id: rootName, label: 'Main Domain', status: 200, type: 'root' },
        { id: 'www.infosoft.poolreno.com', label: 'Web Application', status: 200, type: 'subdomain' },
        { id: 'api.infosoft.poolreno.com', label: 'API Gateway', status: 200, type: 'subdomain' },
        { id: 'cdn.infosoft.poolreno.com', label: 'Static Assets', status: 200, type: 'subdomain' },
        { id: 'mail.infosoft.poolreno.com', label: 'Email Service', status: 200, type: 'external' },
        { id: 'Cloudflare WAF', label: 'WAF Proxy', status: 200, type: 'security' },
        { id: 'MySQL 8.0', label: 'Database', status: 200, type: 'database' },
        { id: 'AWS S3 Bucket', label: 'File Storage', status: 200, type: 'database' },
        { id: 'Cloudflare', label: 'CDN / Security', status: 200, type: 'security' }
    ];
    const links = [
        { source: rootName, target: 'www.infosoft.poolreno.com', type: 'primary' },
        { source: rootName, target: 'api.infosoft.poolreno.com', type: 'primary' },
        { source: rootName, target: 'cdn.infosoft.poolreno.com', type: 'primary' },
        { source: rootName, target: 'mail.infosoft.poolreno.com', type: 'secondary' },
        { source: rootName, target: 'Cloudflare WAF', type: 'security' },
        { source: rootName, target: 'MySQL 8.0', type: 'database' },
        { source: rootName, target: 'AWS S3 Bucket', type: 'database' },
        { source: rootName, target: 'Cloudflare', type: 'security' }
    ];
    return { nodes, links };
});

// Interactive Topology selection
const selectedTopologyNodeId = ref<string>('/');
const handleTopologyNodeClick = (node: any) => {
    selectedTopologyNodeId.value = node.id;
};

// Location flag helper
const getCountryFlag = (location: string) => {
    if (!location) return '🌐';
    const loc = location.toLowerCase();
    if (loc.includes('singapore') || loc.includes('(sg)')) return '🇸🇬';
    if (loc.includes('united states') || loc.includes('(us)') || loc.includes('california')) return '🇺🇸';
    if (loc.includes('japan') || loc.includes('(jp)')) return '🇯🇵';
    if (loc.includes('germany') || loc.includes('(de)')) return '🇩🇪';
    if (loc.includes('united kingdom') || loc.includes('(gb)')) return '🇬🇧';
    return '🌐';
};

const activeTopologyNode = computed(() => {
    const id = selectedTopologyNodeId.value;
    if (id === '/' || id === props.asset.file_name) {
        return {
            id: props.asset.file_name,
            label: 'Main Domain',
            type: 'Web Application',
            status: '200 OK',
            ip: '103.21.244.0',
            protocol: 'HTTPS / TLS 1.3',
            server: 'nginx',
            location: 'Singapore (SG)',
            lastSeen: 'Just now',
            ports: [
                { port: 80, protocol: 'HTTP', active: true },
                { port: 443, protocol: 'HTTPS', active: true },
                { port: 8080, protocol: 'HTTP-Alt', active: false }
            ]
        };
    }
    
    // Check specific nodes matching graph mock layout
    if (id.includes('api.infosoft')) {
        return {
            id: 'api.infosoft.poolreno.com',
            label: 'API Gateway',
            type: 'API Gateway Subdomain',
            status: '200 OK',
            ip: '103.21.244.12',
            protocol: 'HTTPS / TLS 1.3',
            server: 'Go / Fiber',
            location: 'Singapore (SG)',
            lastSeen: '2s ago',
            ports: [
                { port: 443, protocol: 'HTTPS', active: true },
                { port: 80, protocol: 'HTTP', active: true }
            ]
        };
    }
    if (id.includes('cdn.infosoft')) {
        return {
            id: 'cdn.infosoft.poolreno.com',
            label: 'Static Assets',
            type: 'CDN Subdomain',
            status: '200 OK',
            ip: '103.21.244.15',
            protocol: 'HTTPS / TLS 1.3',
            server: 'Nginx',
            location: 'Singapore (SG)',
            lastSeen: '1m ago',
            ports: [
                { port: 443, protocol: 'HTTPS', active: true }
            ]
        };
    }
    if (id.includes('MySQL') || id.includes('database')) {
        return {
            id: 'MySQL 8.0',
            label: 'MySQL Database',
            type: 'Database Engine',
            status: 'Internal Connection',
            ip: '10.0.4.52 (VPC IP)',
            protocol: 'TCP / MySQL Protocol',
            server: 'MySQL 8.0.32-log',
            location: 'Internal VPC Area',
            lastSeen: 'Just now',
            ports: [
                { port: 3306, protocol: 'MySQL', active: true }
            ]
        };
    }
    if (id.includes('S3') || id.includes('Bucket')) {
        return {
            id: 'AWS S3 Bucket',
            label: 'AWS S3 Bucket',
            type: 'File / Storage Node',
            status: 'Active',
            ip: 's3.ap-southeast-1.amazonaws.com',
            protocol: 'HTTPS / S3 REST',
            server: 'AmazonS3',
            location: 'Singapore (SG)',
            lastSeen: '15s ago',
            ports: [
                { port: 443, protocol: 'HTTPS', active: true }
            ]
        };
    }
    if (id.includes('WAF')) {
        return {
            id: 'Cloudflare WAF',
            label: 'Cloudflare WAF',
            type: 'Web Application Firewall',
            status: 'Filtering Traffic',
            ip: '172.67.138.45',
            protocol: 'HTTPS / Anycast',
            server: 'cloudflare',
            location: 'Global Anycast Edge',
            lastSeen: 'Just now',
            ports: [
                { port: 80, protocol: 'HTTP', active: true },
                { port: 443, protocol: 'HTTPS', active: true }
            ]
        };
    }
    if (id.includes('mail')) {
        return {
            id: 'mail.infosoft.poolreno.com',
            label: 'Email Service',
            type: 'Mail Exchanger / SMTP',
            status: 'Active',
            ip: '104.21.32.1',
            protocol: 'SMTP / SMTPS',
            server: 'Postfix Mailer',
            location: 'Singapore (SG)',
            lastSeen: '4m ago',
            ports: [
                { port: 25, protocol: 'SMTP', active: true },
                { port: 587, protocol: 'SMTPS', active: true }
            ]
        };
    }
    
    // Default Fallback
    return {
        id: id,
        label: id,
        type: 'Discovered Dependency',
        status: 'Active',
        ip: '104.21.32.20',
        protocol: 'HTTPS / HTTP',
        server: 'nginx',
        location: 'Singapore (SG)',
        lastSeen: 'Just now',
        ports: [
            { port: 80, protocol: 'HTTP', active: true },
            { port: 443, protocol: 'HTTPS', active: true }
        ]
    };
});

// Findings parser
const keyFindings = computed(() => {
    return [
        { title: 'Outdated framework detected', desc: 'Laravel 11.x with known vulnerabilities.', severity: 'critical', impact: 'High Impact', color: 'text-red-400 bg-rose-950/20 border-rose-500/20' },
        { title: 'Exposed administrative endpoints', desc: '2 admin panels accessible without additional protection.', severity: 'high', impact: 'High Impact', color: 'text-orange-400 bg-orange-950/20 border-orange-500/20' },
        { title: 'Third-party risk', desc: '7 third-party scripts with medium to high risk.', severity: 'high', impact: 'Medium Impact', color: 'text-orange-400 bg-orange-950/20 border-orange-500/20' },
        { title: 'Missing security headers', desc: 'Important security headers are not implemented.', severity: 'medium', impact: 'Medium Impact', color: 'text-yellow-400 bg-yellow-950/20 border-yellow-500/20' },
        { title: 'Information disclosure', desc: 'Server version and technology stack exposed.', severity: 'low', impact: 'Low Impact', color: 'text-emerald-400 bg-emerald-950/20 border-emerald-500/20' }
    ];
});

// Status configuration
const statusInfo = computed(() => {
    const score = props.asset.score ?? 32;
    if (score >= 85) return { label: 'VERIFIED', color: 'border-[#CBB48A] bg-[#CBB48A]/10 text-[#CBB48A]' };
    if (score >= 75) return { label: 'ACTION REQUIRED', color: 'border-amber-500 bg-amber-500/10 text-amber-400' };
    return { label: 'FLAGGED', color: 'border-rose-500/30 bg-rose-500/5 text-rose-400' };
});

const formattedScanDate = computed(() => {
    const d = props.asset.created_at ? new Date(props.asset.created_at) : new Date();
    return {
        date: d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
        time: d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
    };
});

const scanDuration = computed(() => {
    return props.asset.metadata?.scan_duration || '2m 14s';
});

// Close / Back handler
function handleClose() {
    router.visit(route('scans.index'));
}

function handleDownloadReport() {
    window.print();
}

async function refreshSelectedAsset() {
    try {
        const response = await axios.get(`/api/vault/assets/${props.asset.id}`);
        if (response.data?.asset) {
            router.reload({ only: ['asset'] });
        }
    } catch (e) {
        console.error("Error refreshing asset:", e);
    }
}

async function handleDeepScanConfirm(customPrompt: string, isRescan: boolean = false) {
    try {
        await axios.post('/api/vault/deep-audit', {
            asset_id: props.asset.id,
            custom_prompt: customPrompt,
            is_rescan: isRescan
        });
        showQAResultsModal.value = false;
        showSecurityScanningModal.value = true;
    } catch (err) {
        console.error("Deep Scan trigger error:", err);
    }
}

function handleCloseScanningModal() {
    showSecurityScanningModal.value = false;
}
</script>

<template>
    <Head :title="`${props.asset.file_name} - Forensic Results`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-white/[0.01] border border-rose-500/30 flex items-center justify-center text-rose-500 relative shrink-0 shadow-inner">
                        <div class="absolute inset-0 bg-rose-500/5 rounded-xl"></div>
                        <svg class="w-5 h-5 text-rose-500 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <polygon points="12,2 22,7 22,17 12,22 2,17 2,7" fill="none" stroke="currentColor" stroke-width="1.5"/>
                            <circle cx="12" cy="12" r="3" fill="currentColor"/>
                        </svg>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-base font-black text-white truncate max-w-lg tracking-tight leading-tight">
                                {{ props.asset.file_name }}
                            </h2>
                            <span 
                                class="px-2.5 py-0.5 text-xs font-black rounded-full border tracking-widest uppercase"
                                :class="statusInfo.color"
                            >
                                {{ statusInfo.label }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2.5 mt-1 font-mono">
                            <span class="text-xs font-black uppercase tracking-widest px-2.5 py-0.5 rounded border border-[#CBB48A]/30 text-[#CBB48A] bg-[#CBB48A]/5">
                                LUME SOVEREIGN FORENSICS
                            </span>
                            <span class="text-xs text-slate-500 font-bold uppercase tracking-wider">
                                AGENCY PORTFOLIO / SERVICE PORTAL
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        
        </template>


        <div class="space-y-6">
            
            <!-- Breadcrumbs link -->
            <div class="flex items-center gap-2 text-xs font-black font-mono text-slate-500 tracking-wider">
                <span class="hover:text-slate-350 cursor-pointer" @click="handleClose">SCANS</span>
                <span>/</span>
                <span class="text-slate-400">WEBSITE FORENSICS</span>
            </div>

            <!-- Top Stats Banner -->
            <div class="bg-[#070709] border border-white/5 rounded-2xl grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-white/5 overflow-hidden shadow-lg shadow-black/40">
                <!-- Risk Score Card -->
                <div class="lg:col-span-4 p-5 flex items-start gap-4">
                    <div class="relative w-14 h-14 flex items-center justify-center shrink-0">
                        <svg class="absolute inset-0 w-full h-full drop-shadow-[0_0_10px_rgba(239,68,68,0.2)]" viewBox="0 0 100 100">
                            <polygon points="50,5 95,28 95,72 50,95 5,72 5,28" fill="none" stroke="#ef4444" stroke-width="4"/>
                        </svg>
                        <div class="relative text-2xl font-black font-mono text-white pt-0.5">{{ animatedScore }}</div>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Risk Score</span>
                        <span class="text-base font-black mt-1 uppercase text-rose-500">HIGH RISK</span>
                        <p class="text-xs text-slate-400 font-sans mt-1 leading-normal font-semibold">
                            Infosoft appears to be a digital agency managing a highly unstable stack.
                        </p>
                        <div class="mt-2.5">
                            <span class="text-[11px] text-[#CBB48A] font-mono uppercase tracking-wider font-bold bg-[#CBB48A]/5 border border-[#CBB48A]/20 px-2 py-0.5 rounded truncate" title="Software Development Agency / Digital Solutions">Niche: Software Development Agency / Digital Solutions</span>
                        </div>
                    </div>
                </div>

                <!-- Severity Card -->
                <div class="lg:col-span-2 p-5 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center shrink-0 text-rose-500 mt-0.5">
                        <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Severity</span>
                        <span class="text-base font-black text-rose-500 mt-1 uppercase">HIGH</span>
                        <span class="text-xs text-slate-500 mt-0.5 font-semibold leading-tight">Likely to be exploited</span>
                    </div>
                </div>

                <!-- Confidence Card -->
                <div class="lg:col-span-2 p-5 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0 text-amber-500 mt-0.5">
                        <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Confidence</span>
                        <span class="text-base font-black text-amber-500 mt-1">85%</span>
                        <span class="text-xs text-slate-500 mt-0.5 font-semibold leading-tight">High confidence</span>
                    </div>
                </div>

                <!-- Scan Date Card -->
                <div class="lg:col-span-2 p-5 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center shrink-0 text-rose-500 mt-0.5">
                        <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25" />
                        </svg>
                    </div>
                    <div class="flex flex-col font-mono">
                        <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest leading-none">Scan Date</span>
                        <span class="text-base font-black text-white mt-1">Jun 22, 2026</span>
                        <span class="text-xs text-slate-500 mt-0.5 font-bold">4:58 PM</span>
                    </div>
                </div>

                <!-- Duration Card -->
                <div class="lg:col-span-2 p-5 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-800/20 border border-white/5 flex items-center justify-center shrink-0 text-slate-400 mt-0.5">
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Duration</span>
                        <span class="text-base font-black text-white mt-1">2m 14s</span>
                        <span class="text-xs text-slate-500 mt-0.5 font-semibold leading-tight">Full forensic scan</span>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs Bar -->
            <div class="border-b border-white/5 flex items-center justify-between pb-px pt-2">
                <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-1">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        class="px-4 py-3 text-xs font-black tracking-widest transition-all border-b-2 uppercase whitespace-nowrap cursor-pointer text-shadow"
                        :class="activeTab === tab.id ? 'border-[#CBB48A] text-white' : 'border-transparent text-slate-500 hover:text-slate-350'"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <button @click="handleDownloadReport" class="flex items-center gap-1.5 px-4 py-2 border border-white/5 bg-white/[0.01] hover:bg-white/[0.04] hover:border-white/10 text-slate-400 hover:text-white transition-all text-xs font-black uppercase tracking-wider rounded-xl cursor-pointer font-mono shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Download Report
                </button>
            </div>

            <!-- Main Tab Content Area (rendered directly on page background) -->
            <div class="space-y-6 min-h-[450px] pt-2">
                
                <!-- TAB: OVERVIEW -->
                <div v-if="activeTab === 'overview'" class="space-y-6">
                    <!-- Row 1: Risk Breakdown, Forensic Overview, Asset Topology Overview -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- RISK BREAKDOWN -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-base font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 font-mono">
                                        RISK BREAKDOWN
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                </div>
                                <div class="space-y-3.5">
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-xs font-bold text-slate-400">
                                            <span>Critical</span>
                                            <span class="font-mono text-white">8</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-rose-600 rounded-full transition-all duration-500" style="width: 53%;"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-xs font-bold text-slate-400">
                                            <span>High</span>
                                            <span class="font-mono text-white">11</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-pink-500 rounded-full transition-all duration-500" style="width: 73%;"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-xs font-bold text-slate-400">
                                            <span>Medium</span>
                                            <span class="font-mono text-white">7</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-orange-500 rounded-full transition-all duration-500" style="width: 47%;"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-xs font-bold text-slate-400">
                                            <span>Low</span>
                                            <span class="font-mono text-white">4</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-yellow-400 rounded-full transition-all duration-500" style="width: 27%;"></div>
                                        </div>
                                    </div>
                                    <div class="space-y-1 font-sans">
                                        <div class="flex justify-between text-xs font-bold text-slate-400">
                                            <span>Informational</span>
                                            <span class="font-mono text-white">2</span>
                                        </div>
                                        <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-white/5">
                                            <div class="h-full bg-cyan-400 rounded-full transition-all duration-500" style="width: 13%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FORENSIC OVERVIEW -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <h4 class="text-base font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 mb-4 font-mono">
                                    FORENSIC OVERVIEW
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                </h4>
                                <div class="grid grid-cols-4 gap-2 text-center mb-5 font-sans">
                                    <div class="bg-white/[0.01] border border-white/5 rounded-xl p-2 flex flex-col items-center">
                                        <svg class="w-4 h-4 text-rose-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/></svg>
                                        <span class="text-base font-bold text-white font-mono">14</span>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mt-0.5 leading-none">Findings</span>
                                    </div>
                                    <div class="bg-white/[0.01] border border-white/5 rounded-xl p-2 flex flex-col items-center">
                                        <svg class="w-4 h-4 text-emerald-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                        <span class="text-base font-bold text-white font-mono">24</span>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mt-0.5 leading-none">Techs</span>
                                    </div>
                                    <div class="bg-white/[0.01] border border-white/5 rounded-xl p-2 flex flex-col items-center">
                                        <svg class="w-4 h-4 text-blue-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 7h.01M12 17h.01M12 12h.01"/></svg>
                                        <span class="text-base font-bold text-white font-mono">7</span>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mt-0.5 leading-none">Assets</span>
                                    </div>
                                    <div class="bg-white/[0.01] border border-white/5 rounded-xl p-2 flex flex-col items-center">
                                        <svg class="w-4 h-4 text-purple-500 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                                        <span class="text-base font-bold text-white font-mono">8</span>
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mt-0.5 leading-none">Stores</span>
                                    </div>
                                </div>
                                <div class="space-y-3 font-sans">
                                    <div class="flex justify-between items-center text-xs font-bold text-slate-500">
                                        <span>SCAN COVERAGE</span>
                                        <span class="text-orange-400 font-mono">100%</span>
                                    </div>
                                    <div class="w-full bg-slate-950 h-1.5 rounded-full overflow-hidden border border-white/5">
                                        <div class="h-full bg-orange-400 rounded-full" style="width: 100%;"></div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 pt-2 text-xs text-slate-400 font-bold">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Infrastructure <span class="ml-auto font-mono text-white text-xs">100%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Applications <span class="ml-auto font-mono text-white text-xs">100%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Network <span class="ml-auto font-mono text-white text-xs">100%</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Security <span class="ml-auto font-mono text-white text-xs">100%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ASSET TOPOLOGY OVERVIEW -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between relative group shadow-lg shadow-black/40">
                            <div class="absolute top-4 right-4 z-20 flex items-center gap-1.5 bg-[#CBB48A]/5 border border-[#CBB48A]/20 px-2 py-0.5 rounded-full">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#CBB48A] opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-[#CBB48A]"></span>
                                </span>
                                <span class="text-[11px] font-black text-[#CBB48A] tracking-wider uppercase font-mono">Live View</span>
                            </div>
                            <div class="flex justify-between items-center mb-3">
                                <h4 class="text-base font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5 font-mono">
                                    ASSET TOPOLOGY OVERVIEW
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                </h4>
                            </div>
                            <div class="relative w-full h-[120px] bg-black/20 rounded-xl border border-white/5 overflow-hidden">
                                <TopologyGraph :data="topologyData" :hide-hud="true" />
                            </div>
                            <button @click="activeTab = 'topology'" class="w-full mt-3 py-2 border border-white/5 hover:border-white/10 bg-white/[0.01] hover:bg-white/[0.04] rounded-xl text-xs font-black text-slate-400 hover:text-white transition-all uppercase tracking-wider cursor-pointer flex items-center justify-center gap-1.5 border-dashed">
                                View Full Topology
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- EXECUTIVE SUMMARY PANEL -->
                    <div class="w-full p-6 bg-[#070709] border border-white/5 rounded-2xl flex flex-col lg:flex-row items-center justify-between gap-6 relative overflow-hidden shadow-lg shadow-black/40">
                        <div class="absolute inset-y-0 right-0 w-80 bg-gradient-to-l from-red-500/5 to-transparent blur-3xl rounded-full pointer-events-none"></div>
                        
                        <div class="flex-1 space-y-4">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/></svg>
                                <h4 class="text-base font-black text-white uppercase tracking-widest font-mono">EXECUTIVE SUMMARY</h4>
                            </div>
                            <p class="text-base text-slate-450 font-semibold leading-relaxed max-w-2xl font-sans">
                                Infosoft appears to be a digital agency managing a highly unstable technical stack. The infrastructure shows multiple high-risk misconfigurations, outdated components, and exposed sensitive endpoints that could lead to full system compromise.
                            </p>
                            <button @click="activeTab = 'forensic'" class="text-xs font-black text-[#CBB48A] hover:underline uppercase tracking-wider flex items-center gap-1 cursor-pointer font-mono">
                                View full analysis
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-4 w-full lg:w-auto shrink-0 font-mono text-xs">
                            <div class="flex items-center gap-2.5 bg-black/40 border border-white/5 rounded-xl px-4 py-2.5 min-w-[200px]">
                                <div class="w-7 h-7 rounded-lg bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-500 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/></svg></div>
                                <div class="flex flex-col font-sans">
                                    <span class="text-white font-bold uppercase text-xs">HIGH RISK EXPOSURE</span>
                                    <span class="text-slate-500 text-[11px]">Critical issues identified</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5 bg-black/40 border border-white/5 rounded-xl px-4 py-2.5 min-w-[200px]">
                                <div class="w-7 h-7 rounded-lg bg-orange-500/10 border border-orange-500/30 flex items-center justify-center text-orange-500 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></div>
                                <div class="flex flex-col font-sans">
                                    <span class="text-white font-bold uppercase text-xs">ATTACK SURFACE</span>
                                    <span class="text-slate-500 text-[11px]">Wide attack surface detected</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5 bg-black/40 border border-white/5 rounded-xl px-4 py-2.5 min-w-[200px]">
                                <div class="w-7 h-7 rounded-lg bg-purple-500/10 border border-purple-500/30 flex items-center justify-center text-purple-400 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg></div>
                                <div class="flex flex-col font-sans">
                                    <span class="text-white font-bold uppercase text-xs">DATA EXPOSURE</span>
                                    <span class="text-slate-500 text-[11px]">Potential sensitive data at risk</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5 bg-black/40 border border-white/5 rounded-xl px-4 py-2.5 min-w-[200px]">
                                <div class="w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></div>
                                <div class="flex flex-col font-sans">
                                    <span class="text-white font-bold uppercase text-xs">REMEDIATION NEEDED</span>
                                    <span class="text-slate-500 text-[11px]">Immediate actions recommended</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Top Risks, Technology Stack, External Footprint, Activity Feed -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        
                        <!-- TOP RISKS -->
                        <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-base font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 font-mono">
                                        TOP RISKS
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                    <button @click="activeTab = 'forensic'" class="text-xs font-black text-[#CBB48A] hover:underline uppercase tracking-wider cursor-pointer font-mono">View All Findings</button>
                                </div>
                                <div class="space-y-3 font-sans text-base">
                                    <div v-for="(finding, i) in keyFindings" :key="i" class="flex items-center gap-2 pb-2.5 border-b border-white/5 last:border-0 last:pb-0">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="finding.severity === 'critical' ? 'bg-red-500' : 'bg-orange-400'"></span>
                                        <span class="text-slate-300 font-semibold truncate flex-1">{{ finding.title }}</span>
                                        <span class="text-[11px] font-black uppercase tracking-wider px-1.5 py-0.2 rounded font-mono shrink-0 border" :class="finding.severity === 'critical' ? 'border-red-500/30 text-red-400 bg-red-500/5' : 'border-orange-500/30 text-orange-400 bg-orange-500/5'">{{ finding.severity }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TECHNOLOGY STACK -->
                        <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-base font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 font-mono">
                                        TECHNOLOGY STACK
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                    <button @click="activeTab = 'technologies'" class="text-xs font-black text-[#CBB48A] hover:underline uppercase tracking-wider cursor-pointer font-mono">View All</button>
                                </div>
                                <div class="space-y-3 font-mono text-base">
                                    <div v-for="tech in techStack.slice(0, 5)" :key="tech.name" class="flex justify-between items-center py-0.5 border-b border-white/5 last:border-0">
                                        <div class="flex items-center gap-2">
                                            <div class="w-1.5 h-1.5 rounded-full" :style="{ backgroundColor: tech.dot_color }"></div>
                                            <span class="text-slate-300 font-bold text-sm">{{ tech.name }}</span>
                                        </div>
                                        <span class="text-[11px] font-black tracking-wider px-1.5 py-0.5 rounded border border-white/10 text-slate-400 bg-white/[0.01] uppercase">{{ getTechCategory(tech.name) }}</span>
                                    </div>
                                    <div class="text-xs text-slate-500 font-bold tracking-wide text-center pt-2">
                                        + 19 more technologies
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- EXTERNAL FOOTPRINT -->
                        <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-base font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 font-mono">
                                        EXTERNAL FOOTPRINT
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                    <button @click="activeTab = 'evidence'" class="text-xs font-black text-[#CBB48A] hover:underline uppercase tracking-wider cursor-pointer font-mono">View Details</button>
                                </div>
                                <div class="space-y-3 font-mono text-base">
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Subdomains</span>
                                        <span class="text-white font-bold">11</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Open Ports</span>
                                        <span class="text-white font-bold">6</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">IP Addresses</span>
                                        <span class="text-white font-bold">4</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">SSL/TLS</span>
                                        <span class="text-emerald-400 font-bold flex items-center gap-1.5 text-xs">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Valid
                                        </span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Hosting Provider</span>
                                        <span class="text-white font-bold text-xs truncate max-w-[100px] text-right" title="Cloudflare, Inc.">Cloudflare, Inc.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ACTIVITY FEED -->
                        <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-base font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 font-mono">
                                        ACTIVITY FEED
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 1 1 1.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                    </h4>
                                    <button @click="activeTab = 'history'" class="text-xs font-black text-[#CBB48A] hover:underline uppercase tracking-wider cursor-pointer font-mono">View All</button>
                                </div>
                                <div class="space-y-3 font-sans text-base">
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Scan completed</span>
                                            <span class="text-xs text-slate-500 font-mono mt-0.5">2m ago</span>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Evidence collected</span>
                                            <span class="text-xs text-slate-500 font-mono mt-0.5">3m ago</span>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Topology mapped</span>
                                            <span class="text-xs text-slate-500 font-mono mt-0.5">3m ago</span>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Technologies detected</span>
                                            <span class="text-xs text-slate-500 font-mono mt-0.5">4m ago</span>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mt-1.5"></span>
                                        <div class="flex flex-col">
                                            <span class="text-slate-300 font-semibold leading-tight">Network analysis complete</span>
                                            <span class="text-xs text-slate-500 font-mono mt-0.5">4m ago</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB: FORENSIC ANALYSIS -->
                <div v-if="activeTab === 'forensic'" class="space-y-6">
                    <!-- Header Details -->
                    <div class="flex-shrink-0">
                        <h4 class="text-lg font-black text-white uppercase tracking-widest font-mono">FORENSIC ANALYSIS DETAIL</h4>
                        <p class="text-base text-slate-500 font-medium font-sans mt-0.5">In-depth analysis of the asset's architecture, configuration and operational posture.</p>
                    </div>
                    
                    <!-- Row 1: Narrative vs Business Context -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 p-6 bg-[#070709] rounded-2xl border border-white/5 flex gap-4 items-start shadow-lg shadow-black/40">
                            <div class="w-12 h-12 rounded-full bg-[#CBB48A]/10 border border-[#CBB48A]/30 flex items-center justify-center text-[#CBB48A] shrink-0">
                                <svg class="w-6 h-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21l8.982-11.795M10.896 11H18l-9 9.25" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m0-12.728l.707.707m11.314 11.314l.707-.707" />
                                </svg>
                            </div>
                            <div class="flex flex-col space-y-1">
                                <h5 class="text-base font-black text-[#CBB48A] uppercase tracking-wider font-mono">Architecture Narrative</h5>
                                <p class="text-base text-slate-300 leading-relaxed font-medium font-sans">
                                    The application exhibits severe 'Framework Bloat' and architectural incoherence, simultaneously attempting to leverage Laravel, WordPress, and Django. This suggests a fragmented legacy environment or a 'Frankenstein' deployment where multiple disparate systems are being proxied under a single domain. The presence of both Vue.js and React further indicates a lack of a unified frontend strategy, likely resulting in significant technical debt and maintenance overhead.
                                </p>
                            </div>
                        </div>
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-start space-y-3 shadow-lg shadow-black/40">
                            <h5 class="text-base font-black text-indigo-400 uppercase tracking-wider font-mono">Business Context</h5>
                            <div class="border-l border-indigo-500/40 pl-4 py-0.5">
                                <p class="text-base text-slate-400 leading-relaxed font-medium font-sans">
                                    Infosoft appears to be a digital agency managing a highly unstable technical stack. The current infrastructure is not suitable for enterprise-grade scaling due to conflicting backend technologies.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Key Findings vs Radar & Supply Chain -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        <!-- KEY FINDINGS -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                            <div>
                                <div class="flex justify-between items-center mb-4">
                                    <h5 class="text-base font-black text-white uppercase tracking-widest font-mono">Key Findings <span class="ml-1 text-xs text-slate-500 font-bold bg-white/5 px-2 py-0.5 rounded font-sans">37 TOTAL</span></h5>
                                </div>
                                <div class="space-y-3 font-sans text-base">
                                    <div v-for="(finding, i) in keyFindings" :key="i" class="flex items-center justify-between p-3 bg-white/[0.01] border border-white/5 rounded-xl hover:bg-white/[0.03] transition-all cursor-pointer">
                                        <!-- Left side: Icon + Text -->
                                        <div class="flex items-center gap-3 min-w-0 flex-1 mr-4">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 border bg-slate-950"
                                                 :class="{
                                                     'border-rose-500/20 text-rose-500': finding.severity === 'critical',
                                                     'border-orange-500/20 text-orange-405': finding.severity === 'high',
                                                     'border-yellow-500/20 text-yellow-450': finding.severity === 'medium',
                                                     'border-emerald-500/20 text-emerald-450': finding.severity === 'low',
                                                 }">
                                                <svg v-if="finding.severity === 'critical'" class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3Z"/></svg>
                                                <svg v-else-if="finding.severity === 'high'" class="w-4 h-4 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                                <svg v-else-if="finding.severity === 'medium'" class="w-4 h-4 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                                                <svg v-else class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.063.852l-.708 2.836a.75.75 0 001.063.852l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                            </div>
                                            <div class="min-w-0">
                                                <h6 class="text-base font-bold text-white truncate">{{ finding.title }}</h6>
                                                <p class="text-xs text-slate-400 font-semibold truncate mt-0.5 leading-relaxed">{{ finding.desc }}</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Right side: Severity Badge, Impact, Arrow -->
                                        <div class="flex items-center gap-6 shrink-0 text-xs font-mono font-bold">
                                            <!-- Severity Dot + Label -->
                                            <div class="flex items-center gap-1.5 min-w-[70px]">
                                                <span class="w-1.5 h-1.5 rounded-full" 
                                                      :class="{
                                                          'bg-rose-500': finding.severity === 'critical',
                                                          'bg-orange-400': finding.severity === 'high',
                                                          'bg-yellow-400': finding.severity === 'medium',
                                                          'bg-emerald-400': finding.severity === 'low',
                                                      }"></span>
                                                <span :class="{
                                                          'text-rose-450': finding.severity === 'critical',
                                                          'text-orange-400': finding.severity === 'high',
                                                          'text-yellow-405': finding.severity === 'medium',
                                                          'text-emerald-400': finding.severity === 'low',
                                                      }">
                                                    {{ finding.severity.toUpperCase().charAt(0) + finding.severity.slice(1) }}
                                                </span>
                                            </div>
                                            
                                            <!-- Impact text -->
                                            <span class="text-slate-500 min-w-[75px] text-right font-semibold">{{ finding.impact }}</span>
                                            
                                            <!-- Chevron -->
                                            <svg class="w-3.5 h-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-center mt-4">
                                    <button @click="showAIDetailsModal = true" class="px-5 py-2 border border-white/5 hover:border-white/10 bg-white/[0.01] hover:bg-white/[0.04] rounded-xl text-xs font-black text-slate-400 hover:text-white transition-all uppercase tracking-wider flex items-center justify-center gap-1.5 cursor-pointer font-mono">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        View All Findings (37)
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- IMPACT & RISK SUMMARY (Radar) -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 grid grid-cols-1 md:grid-cols-12 gap-6 relative shadow-lg shadow-black/40">
                            <div class="md:col-span-7 flex flex-col">
                                <div class="flex items-center gap-2 mb-3">
                                    <div class="w-5 h-5 flex items-center justify-center shrink-0 text-[#CBB48A]">
                                        <svg class="w-4 h-4" viewBox="0 0 100 100" fill="none">
                                            <polygon points="50,8 86,29 86,71 50,92 14,71 14,29" stroke="currentColor" stroke-width="8" />
                                            <circle cx="50" cy="50" r="18" fill="currentColor" />
                                        </svg>
                                    </div>
                                    <h5 class="text-base font-black text-white uppercase tracking-widest font-mono">IMPACT & RISK SUMMARY</h5>
                                </div>
                                <div class="relative w-full flex-1 flex items-center justify-center mx-auto select-none min-h-[290px]">
                                    <svg class="w-full h-full max-h-[300px]" viewBox="0 0 380 280">
                                        <defs>
                                            <!-- Gradient for the radar sweep trail (gold/amber) -->
                                            <linearGradient id="radarSweepGradient" x1="100%" y1="100%" x2="0%" y2="0%">
                                                <stop offset="0%" stop-color="#CBB48A" stop-opacity="0.3" />
                                                <stop offset="100%" stop-color="#CBB48A" stop-opacity="0" />
                                            </linearGradient>
                                        </defs>

                                        <!-- Concentric background circles for radar level guide lines -->
                                        <circle v-for="level in [1, 2, 3, 4, 5]" :key="level" :cx="cx" :cy="cy" :r="(level / 5) * rMax" fill="none" stroke="rgba(203, 180, 138, 0.1)" stroke-width="1" />
                                        
                                        <!-- Outer circular bounds border -->
                                        <circle :cx="cx" :cy="cy" :r="rMax" fill="none" stroke="rgba(203, 180, 138, 0.3)" stroke-width="1.5" />

                                        <!-- Web Axis grid lines linking center to maximum values -->
                                        <line v-for="idx in 5" :key="'axis-' + idx" :x1="cx" :y1="cy" :x2="getAxisCoords(idx - 1).x" :y2="getAxisCoords(idx - 1).y" stroke="rgba(203, 180, 138, 0.18)" stroke-width="1" />
                                        
                                        <!-- Rotating Radar Sweep Line & Wedge flashlight effect -->
                                        <g class="radar-sweep-effect">
                                            <!-- Sweep trailing sector wedge -->
                                            <path :d="`M ${cx} ${cy} L ${cx} ${cy - rMax} A ${rMax} ${rMax} 0 0 0 ${cx - rMax * 0.588} ${cy - rMax * 0.809} Z`" fill="url(#radarSweepGradient)" />
                                            <!-- Glowing sweep beam line -->
                                            <line :x1="cx" :y1="cy" :x2="cx" :y2="cy - rMax" stroke="#CBB48A" stroke-width="1.5" stroke-linecap="round" />
                                        </g>

                                        <!-- Shaded Pentagonal Radar Area -->
                                        <polygon :points="polygonPoints" fill="rgba(203, 180, 138, 0.18)" stroke="#CBB48A" stroke-width="2" />
                                        
                                        <!-- Point marker pins on the data line -->
                                        <circle v-for="(val, idx) in radarValues" :key="'marker-' + idx" :cx="getCoords(val, idx).x" :cy="getCoords(val, idx).y" r="3" fill="#CBB48A" stroke="#070709" stroke-width="1" />

                                        <!-- Glowing red center dot -->
                                        <circle :cx="cx" :cy="cy" r="6" fill="#ef4444" fill-opacity="0.25" class="animate-pulse" />
                                        <circle :cx="cx" :cy="cy" r="3" fill="#ef4444" />

                                        <!-- Custom labels placement matching layout positions -->
                                        <!-- 1. Architecture -->
                                        <text :x="cx" :y="cy - rMax - 14" text-anchor="middle" class="font-mono text-xs font-bold" fill="#94a3b8">
                                            <tspan :x="cx" dy="0" fill="#94a3b8">Architecture</tspan>
                                            <tspan :x="cx" dy="12" fill="#CBB48A">{{ radarValues[0] }}/100</tspan>
                                        </text>

                                        <!-- 2. Access Control -->
                                        <text :x="cx + rMax + 24" :y="cy - 8" text-anchor="middle" class="font-mono text-xs font-bold" fill="#94a3b8">
                                            <tspan :x="cx + rMax + 24" dy="0" fill="#94a3b8">Access Control</tspan>
                                            <tspan :x="cx + rMax + 24" dy="12" fill="#CBB48A">{{ radarValues[1] }}/100</tspan>
                                        </text>

                                        <!-- 3. Data Exposure -->
                                        <text :x="cx + rMax - 18" :y="cy + rMax + 14" text-anchor="middle" class="font-mono text-xs font-bold" fill="#94a3b8">
                                            <tspan :x="cx + rMax - 18" dy="0" fill="#94a3b8">Data Exposure</tspan>
                                            <tspan :x="cx + rMax - 18" dy="12" fill="#CBB48A">{{ radarValues[2] }}/100</tspan>
                                        </text>

                                        <!-- 4. Security Posture -->
                                        <text :x="cx - rMax + 18" :y="cy + rMax + 14" text-anchor="middle" class="font-mono text-xs font-bold" fill="#94a3b8">
                                            <tspan :x="cx - rMax + 18" dy="0" fill="#94a3b8">Security Posture</tspan>
                                            <tspan :x="cx - rMax + 18" dy="12" fill="#CBB48A">{{ radarValues[3] }}/100</tspan>
                                        </text>

                                        <!-- 5. Third-Party -->
                                        <text :x="cx - rMax - 24" :y="cy - 8" text-anchor="middle" class="font-mono text-xs font-bold" fill="#94a3b8">
                                            <tspan :x="cx - rMax - 24" dy="0" fill="#94a3b8">Third-Party</tspan>
                                            <tspan :x="cx - rMax - 24" dy="12" fill="#CBB48A">{{ radarValues[4] }}/100</tspan>
                                        </text>
                                    </svg>
                                </div>
                            </div>
                            <div class="md:col-span-5 flex flex-col justify-between space-y-4">
                                <!-- Overall Impact Card -->
                                <div class="flex-1 p-4 bg-black/40 border border-white/5 rounded-xl flex flex-col justify-center space-y-1.5">
                                    <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Overall Impact</span>
                                    <span class="text-2xl font-black text-rose-500 uppercase tracking-tight mt-1">High</span>
                                    <p class="text-xs text-slate-450 leading-relaxed font-semibold font-sans mt-1">
                                        Multiple high-risk issues impacting integrity, confidentiality and availability.
                                    </p>
                                </div>
                                
                                <!-- Supply Chain Score Card -->
                                <div class="flex-1 p-4 bg-black/40 border border-white/5 rounded-xl flex flex-col justify-center space-y-1.5">
                                    <span class="text-[11px] font-black text-slate-500 uppercase tracking-widest leading-none font-mono">Supply Chain Score</span>
                                    <div class="flex items-baseline gap-1 mt-1">
                                        <span class="text-2xl font-black text-rose-500 font-mono">20</span>
                                        <span class="text-[#CBB48A] text-base font-bold font-mono">/100</span>
                                    </div>
                                    <p class="text-xs text-slate-450 leading-relaxed font-semibold font-sans mt-1">
                                        Low integrity across third-party dependencies.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Network Signals vs Compliance & Privacy -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        
                        <!-- NETWORK SIGNALS -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 grid grid-cols-1 md:grid-cols-12 gap-6 items-stretch shadow-lg shadow-black/40">
                            <div class="md:col-span-7 space-y-4">
                                <h5 class="text-base font-black text-slate-400 uppercase tracking-widest font-mono">NETWORK SIGNALS</h5>
                                <div class="space-y-3 font-mono text-base">
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Protocol</span>
                                        <span class="text-white font-bold">HTTPS/TLS 1.3</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">DNS Authority</span>
                                        <span class="text-white font-bold">Cloudflare</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Server Signature</span>
                                        <span class="text-white font-bold">nginx</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">CDN / WAF</span>
                                        <span class="text-white font-bold">Cloudflare</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">IP Reputation</span>
                                        <span class="text-emerald-400 font-bold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_6px_#10b981]"></span>
                                            Clean
                                        </span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Geo Location</span>
                                        <span class="text-white font-bold">{{ geoData.country }} ({{ geoData.countryCode }})</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Map Panel: D3.js Realistic World Map -->
                            <div class="md:col-span-5 relative rounded-xl overflow-hidden bg-[#07080a] border border-white/5 h-full min-h-[220px]">
                                <!-- D3 SVG Thumbnail - filled by drawD3Map() -->
                                <svg ref="mapThumbnailRef" class="w-full h-full block"></svg>

                                <!-- View Map Button — bottom-left -->
                                <button
                                    @click="openMapModal"
                                    class="absolute bottom-3 left-3 flex items-center gap-1.5 bg-[#CBB48A]/10 hover:bg-[#CBB48A]/20 border border-[#CBB48A]/30 text-[#CBB48A] text-[10px] font-bold font-mono px-2.5 py-1.5 rounded transition-all duration-200 backdrop-blur-md uppercase tracking-wider"
                                >
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                    </svg>
                                    View Map
                                </button>

                                <!-- Geo Location Badge — matches second image exactly -->
                                <div class="absolute bottom-3 right-3 bg-[#0d0e11]/90 border border-[#CBB48A]/30 px-3.5 py-2 rounded-xl text-left shadow-lg backdrop-blur-md max-w-[170px]">
                                    <div class="text-[10px] text-[#CBB48A]/60 font-semibold uppercase tracking-widest font-mono">Geo Location</div>
                                    <div class="text-xs text-white font-bold font-mono tracking-wide mt-0.5">{{ geoData.country }} ({{ geoData.countryCode }})</div>
                                </div>
                            </div>
                        </div>

                        <!-- ======================================================= -->
                        <!-- FULL-SCREEN MAP MODAL                                   -->
                        <!-- ======================================================= -->
                        <Teleport to="body">
                            <Transition name="map-modal">
                                <div v-if="showMapModal" class="fixed inset-0 z-[9999] w-screen h-screen bg-[#060709] flex flex-col font-mono text-sm text-slate-300 overflow-hidden">
                                    
                                    <!-- Header -->
                                    <div class="flex items-center justify-between px-6 py-4 border-b border-white/5 bg-[#07080a] flex-shrink-0 gap-4">
                                        <div class="flex items-center gap-4">
                                            <button @click="showMapModal = false" class="w-9 h-9 rounded-lg border border-white/10 flex items-center justify-center text-slate-400 hover:text-white hover:border-white/20 transition-colors flex-shrink-0 bg-white/5" title="Close Map">
                                                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                            <div>
                                                <h3 class="text-base md:text-lg font-black text-white font-mono uppercase tracking-widest">SERVER NETWORK MAP</h3>
                                                <p class="text-xs text-slate-400 font-mono mt-0.5">Surface-level topology • External connections detected via DNS/CDN/HTTP analysis</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-6">
                                            <!-- Legend -->
                                            <div class="hidden sm:flex items-center gap-3">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-[#CBB48A]"></span>
                                                    <span class="text-xs text-slate-400">Primary Server</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-[#22d3ee]"></span>
                                                    <span class="text-xs text-slate-400">CDN / Proxy</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-[#c084fc]"></span>
                                                    <span class="text-xs text-slate-400">Analytics</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-[#60a5fa]"></span>
                                                    <span class="text-xs text-slate-400">Cloud</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-[#4ade80]"></span>
                                                    <span class="text-xs text-slate-400">Payment</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="w-2 h-2 rounded-full bg-[#f472b6]"></span>
                                                    <span class="text-xs text-slate-400">Social</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Main Content Body -->
                                    <div class="flex-1 min-h-0 w-full grid grid-cols-12 gap-6 p-4 md:p-6 overflow-y-auto lg:overflow-hidden lg:h-full">
                                        
                                        <!-- Left Column: Map/List View + Stats (grid-span-9) -->
                                        <div class="col-span-12 lg:col-span-9 flex flex-col lg:h-full lg:overflow-hidden gap-4">
                                            
                                            <!-- Toolbar Controls -->
                                            <div class="flex items-center justify-between">
                                                <!-- Map / List Selector Toggle -->
                                                <div class="flex items-center bg-black/40 rounded-lg p-0.5 border border-white/5">
                                                    <button 
                                                        @click="activeMapView = 'map'" 
                                                        :class="[
                                                            'px-3.5 py-1.5 rounded-md text-xs font-bold uppercase tracking-wider transition-all',
                                                            activeMapView === 'map' ? 'bg-[#CBB48A]/20 text-[#CBB48A] border border-[#CBB48A]/30' : 'text-slate-400 border border-transparent hover:text-white'
                                                        ]"
                                                    >
                                                        Map
                                                    </button>
                                                    <button 
                                                        @click="activeMapView = 'list'" 
                                                        :class="[
                                                            'px-3.5 py-1.5 rounded-md text-xs font-bold uppercase tracking-wider transition-all',
                                                            activeMapView === 'list' ? 'bg-[#CBB48A]/20 text-[#CBB48A] border border-[#CBB48A]/30' : 'text-slate-400 border border-transparent hover:text-white'
                                                        ]"
                                                    >
                                                        List
                                                    </button>
                                                </div>

                                                <!-- Map Controls -->
                                                <div class="flex items-center gap-1.5" v-if="activeMapView === 'map'">
                                                    <button @click="handleZoomReset" class="bg-[#0c0d10] border border-white/5 text-slate-400 hover:text-white text-xs px-3 py-1.5 rounded hover:bg-white/5 transition-all">Fit to View</button>
                                                    <button @click="handleZoomIn" class="bg-[#0c0d10] border border-white/5 text-slate-400 hover:text-white text-sm font-bold w-8 h-8 flex items-center justify-center rounded hover:bg-white/5 transition-all">+</button>
                                                    <button @click="handleZoomOut" class="bg-[#0c0d10] border border-white/5 text-slate-400 hover:text-white text-sm font-bold w-8 h-8 flex items-center justify-center rounded hover:bg-white/5 transition-all">-</button>
                                                    <button @click="handleZoomReset" class="bg-[#0c0d10] border border-white/5 text-slate-400 hover:text-white text-xs p-2 rounded hover:bg-white/5 transition-all">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75v4.5m0-4.5h-4.5m4.5 0L15 9m5.25 11.25v-4.5m0 4.5h-4.5m4.5 0L15 15"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Map Display Container -->
                                            <div class="flex-1 relative rounded-xl border border-white/5 bg-[#07080a] overflow-hidden min-h-[350px]">
                                                <!-- MAP VIEW -->
                                                <div v-show="activeMapView === 'map'" class="w-full h-full">
                                                    <svg ref="mapModalRef" class="w-full h-full block"></svg>
                                                    
                                                    <!-- SURFACE-LEVEL ANALYSIS note bottom-left -->
                                                    <div class="absolute bottom-3 left-3 bg-[#0d0e11]/95 border border-[#CBB48A]/20 px-3.5 py-2.5 rounded-lg backdrop-blur-md max-w-xs shadow-lg">
                                                        <div class="flex items-start gap-2">
                                                            <svg class="w-4 h-4 text-[#CBB48A] mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/>
                                                            </svg>
                                                            <div>
                                                                <div class="text-xs text-[#CBB48A] font-bold uppercase tracking-widest font-mono">Surface-Level Analysis</div>
                                                                <div class="text-xs text-slate-400 font-mono mt-0.5 leading-normal">Connections mapped via DNS, CDN headers & HTTP metadata. Deep packet inspection requires privileged access.</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- LIST VIEW -->
                                                <div v-show="activeMapView === 'list'" class="w-full h-full p-4 overflow-y-auto modal-scrollbar">
                                                    <h4 class="text-sm font-bold text-white mb-3 uppercase tracking-wider border-b border-white/5 pb-2">Network Nodes List</h4>
                                                    <table class="w-full text-left font-mono border-collapse text-xs md:text-sm">
                                                        <thead>
                                                            <tr class="text-slate-500 border-b border-white/10 text-xs">
                                                                <th class="py-2">NODE TYPE</th>
                                                                <th class="py-2">HOST/DOMAIN</th>
                                                                <th class="py-2">LOCATION / COUNTRY</th>
                                                                <th class="py-2">COORDINATES</th>
                                                                <th class="py-2">TYPE / CATEGORY</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-white/5">
                                                            <!-- Primary server -->
                                                            <tr class="hover:bg-white/5 transition-colors text-white">
                                                                <td class="py-2 text-[#CBB48A] font-bold">Primary Server</td>
                                                                <td class="py-2">{{ props.asset.file_name }}</td>
                                                                <td class="py-2">{{ geoData.country }} ({{ geoData.countryCode }})</td>
                                                                <td class="py-2 font-mono text-xs">{{ geoData.lat.toFixed(4) }} N, {{ geoData.lon.toFixed(4) }} E</td>
                                                                <td class="py-2">
                                                                    <span class="bg-[#CBB48A]/10 text-[#CBB48A] text-xs px-1.5 py-0.5 rounded font-bold">PRIMARY</span>
                                                                </td>
                                                            </tr>
                                                            <!-- External connections -->
                                                            <tr v-for="conn in externalConnections" :key="conn.host" class="hover:bg-white/5 transition-colors">
                                                                <td class="py-2 text-slate-400">External Node</td>
                                                                <td class="py-2 text-slate-200">{{ conn.host }}</td>
                                                                <td class="py-2 text-slate-300">{{ conn.label }}</td>
                                                                <td class="py-2 text-slate-300 font-mono text-xs">{{ conn.lat ? (Math.abs(conn.lat).toFixed(4) + (conn.lat > 0 ? ' N' : ' S')) : '0.0000' }}, {{ conn.lon ? (Math.abs(conn.lon).toFixed(4) + (conn.lon > 0 ? ' E' : ' W')) : '0.0000' }}</td>
                                                                <td class="py-2">
                                                                    <span 
                                                                        :style="{ color: connectionTypeColor[conn.type] || '#64748b', background: (connectionTypeColor[conn.type] || '#64748b') + '15' }"
                                                                        class="text-xs px-1.5 py-0.5 rounded font-bold uppercase"
                                                                    >
                                                                        {{ conn.type }}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Bottom Cards Row -->
                                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mt-1 flex-shrink-0">
                                                
                                                <!-- Card 1: Connection Summary -->
                                                <div class="bg-[#0b0c10] border border-white/5 rounded-xl p-3.5 flex flex-col justify-between">
                                                    <h4 class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-2">Connection Summary</h4>
                                                    <div class="space-y-1.5 text-xs md:text-sm">
                                                        <div class="flex justify-between">
                                                            <span class="text-slate-400">Total Connections</span>
                                                            <span class="text-white font-bold">{{ externalConnections.length + 1 }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-slate-400">Countries</span>
                                                            <span class="text-white font-bold">{{ uniqueCountriesCount }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-slate-400">ASNs</span>
                                                            <span class="text-white font-bold">{{ uniqueAsnsCount }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-slate-400">Protocols</span>
                                                            <span class="text-white font-bold">3</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-slate-400">CDN / WAF</span>
                                                            <span class="text-cyan-400 font-bold">Yes</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-slate-400">Anonymization</span>
                                                            <span class="text-emerald-400 font-bold">Low</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Card 2: Top Connection Countries -->
                                                <div class="bg-[#0b0c10] border border-white/5 rounded-xl p-3.5 flex flex-col justify-between">
                                                    <h4 class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-2">Top Countries</h4>
                                                    <div class="space-y-1 text-xs overflow-y-auto max-h-[100px] pr-0.5 modal-scrollbar">
                                                        <div v-for="(count, country) in countryDistribution" :key="country" class="flex justify-between items-center py-0.5">
                                                            <span class="text-slate-300 truncate max-w-[100px] flex items-center gap-1.5">
                                                                <span class="text-xs">
                                                                    {{ country === 'Singapore' ? '🇸🇬' : (country === 'Japan' ? '🇯🇵' : (country === 'Australia' ? '🇦🇺' : (country === 'Germany' ? '🇩🇪' : '🇺🇸'))) }}
                                                                </span>
                                                                {{ country }}
                                                            </span>
                                                            <span class="text-white font-bold">{{ count }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Card 3: Protocol Distribution (SVG Donut Chart) -->
                                                <div class="bg-[#0b0c10] border border-white/5 rounded-xl p-3.5 flex items-center justify-between">
                                                    <div class="flex flex-col justify-between h-full w-full">
                                                        <h4 class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-2">Protocol Distribution</h4>
                                                        <div class="flex items-center gap-3">
                                                            <!-- Small SVG Donut Chart -->
                                                            <div class="relative w-12 h-12 flex-shrink-0">
                                                                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                                                                    <circle cx="18" cy="18" r="15.915" fill="none" stroke="#1c1d24" stroke-width="3"></circle>
                                                                    <!-- HTTPS Segment (approx 75%) -->
                                                                    <circle cx="18" cy="18" r="15.915" fill="none" stroke="#60a5fa" stroke-width="3.2" stroke-dasharray="75 25" stroke-dashoffset="0"></circle>
                                                                    <!-- HTTP Segment (approx 12.5%) -->
                                                                    <circle cx="18" cy="18" r="15.915" fill="none" stroke="#f472b6" stroke-width="3.2" stroke-dasharray="12.5 87.5" stroke-dashoffset="-75"></circle>
                                                                    <!-- DNS Segment (approx 12.5%) -->
                                                                    <circle cx="18" cy="18" r="15.915" fill="none" stroke="#facc15" stroke-width="3.2" stroke-dasharray="12.5 87.5" stroke-dashoffset="-87.5"></circle>
                                                                </svg>
                                                                <!-- Label in middle -->
                                                                <div class="absolute inset-0 flex flex-col items-center justify-center leading-none text-white">
                                                                    <span class="text-xs font-black font-mono">{{ externalConnections.length + 1 }}</span>
                                                                    <span class="text-[9px] text-slate-500 font-mono mt-0.5">Total</span>
                                                                </div>
                                                            </div>
                                                            <div class="space-y-0.5 text-xs font-mono">
                                                                <div class="flex items-center gap-1">
                                                                    <span class="w-1.5 h-1.5 bg-blue-400 rounded-sm"></span>
                                                                    <span class="text-slate-400">HTTPS 75%</span>
                                                                </div>
                                                                <div class="flex items-center gap-1">
                                                                    <span class="w-1.5 h-1.5 bg-pink-400 rounded-sm"></span>
                                                                    <span class="text-slate-400">HTTP 12.5%</span>
                                                                </div>
                                                                <div class="flex items-center gap-1">
                                                                    <span class="w-1.5 h-1.5 bg-yellow-400 rounded-sm"></span>
                                                                    <span class="text-slate-400">DNS 12.5%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Card 4: Connection Types -->
                                                <div class="bg-[#0b0c10] border border-white/5 rounded-xl p-3.5 flex flex-col justify-between">
                                                    <h4 class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-2">Connection Types</h4>
                                                    <div class="space-y-1 text-xs overflow-y-auto max-h-[100px] pr-0.5 modal-scrollbar">
                                                        <div v-for="(count, type) in connectionTypesCount" :key="type" class="flex justify-between items-center py-0.5">
                                                            <span class="text-slate-300 truncate max-w-[100px] flex items-center gap-1.5">
                                                                <span class="w-1.5 h-1.5 rounded-full" :style="{ background: connectionTypeColor[type] || '#64748b' }"></span>
                                                                <span class="capitalize">{{ type }}</span>
                                                            </span>
                                                            <span class="text-white font-bold">{{ count }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>

                                        </div>

                                        <!-- Right Column: Sidebar Node Details (grid-span-3) -->
                                        <div class="col-span-12 lg:col-span-3 flex flex-col gap-4 lg:overflow-y-auto lg:h-full pr-1 pb-4 border-t lg:border-t-0 lg:border-l border-white/5 pt-4 lg:pt-0 lg:pl-6 modal-scrollbar">
                                            
                                            <!-- Primary Server details card -->
                                            <div class="bg-[#0c0d10] border border-white/5 rounded-xl p-4 flex flex-col gap-3 flex-shrink-0">
                                                <div class="flex items-center justify-between">
                                                    <h4 class="text-xs text-slate-500 font-bold uppercase tracking-wider">Primary Server</h4>
                                                    <span class="flex items-center gap-1 text-xs text-emerald-400 font-mono">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_6px_#10b981]"></span>
                                                        Active
                                                    </span>
                                                </div>
                                                
                                                <!-- Flag and Domain -->
                                                <div class="flex items-start gap-2 border-b border-white/5 pb-3">
                                                    <span class="text-2xl mt-0.5 flex-shrink-0">
                                                        {{ geoData.country === 'Singapore' ? '🇸🇬' : (geoData.country === 'Japan' ? '🇯🇵' : (geoData.country === 'Australia' ? '🇦🇺' : (geoData.country === 'Germany' ? '🇩🇪' : '🇺🇸'))) }}
                                                    </span>
                                                    <div class="min-w-0">
                                                        <div class="text-sm md:text-base text-white font-black truncate font-mono">{{ props.asset.file_name }}</div>
                                                        <div class="text-xs text-slate-400 font-mono mt-0.5">{{ geoData.ip }}</div>
                                                        <div class="text-xs text-slate-500 font-mono flex items-center gap-1 mt-1">
                                                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                                            </svg>
                                                            {{ geoData.isp }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Key Value parameters -->
                                                <div class="space-y-2 text-xs md:text-sm">
                                                    <div class="flex justify-between">
                                                        <span class="text-slate-505">ASN</span>
                                                        <span class="text-slate-300 font-mono truncate max-w-[130px]">{{ geoData.as.split(' ')[0] }}</span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-slate-505">ISP</span>
                                                        <span class="text-slate-300 truncate max-w-[130px]">{{ geoData.isp }}</span>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-slate-550">Last Seen</span>
                                                        <span class="text-slate-300 font-mono">{{ formattedLastSeen }}</span>
                                                    </div>
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-slate-505">Open Ports</span>
                                                        <div class="flex gap-1">
                                                            <span class="bg-black/80 border border-white/10 px-1.5 py-0.5 rounded font-mono text-[10px] text-white">80</span>
                                                            <span class="bg-black/80 border border-white/10 px-1.5 py-0.5 rounded font-mono text-[10px] text-white">443</span>
                                                        </div>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <span class="text-slate-505">Protocol</span>
                                                        <span class="text-slate-300 font-mono">HTTP, HTTPS</span>
                                                    </div>
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-slate-550">Reputation</span>
                                                        <span class="text-emerald-400 flex items-center gap-1 font-bold">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                            Clean
                                                        </span>
                                                    </div>
                                                </div>

                                            </div>

                                            <!-- External Connections list sidebar card -->
                                            <div class="bg-[#0c0d10] border border-white/5 rounded-xl p-4 flex flex-col gap-3 flex-shrink-0">
                                                <div class="flex justify-between items-center">
                                                    <h4 class="text-xs text-slate-500 font-bold uppercase tracking-wider">External Connections ({{ externalConnections.length }})</h4>
                                                </div>
                                                
                                                <div class="space-y-2 max-h-[160px] overflow-y-auto pr-0.5 modal-scrollbar">
                                                    <div v-for="conn in externalConnections" :key="conn.host" class="flex items-center justify-between p-1.5 rounded bg-black/35 border border-white/5">
                                                        <div class="min-w-0 flex items-center gap-1.5">
                                                            <span class="text-xs">
                                                                {{ conn.type === 'cdn' ? '🇯🇵' : (conn.type === 'payment' ? '🇦🇺' : (conn.label.includes('Akamai') || conn.label.includes('Edge') ? '🇩🇪' : '🇺🇸')) }}
                                                            </span>
                                                            <div class="min-w-0 leading-none">
                                                                <div class="text-xs text-white font-bold font-mono truncate max-w-[125px]">{{ conn.host }}</div>
                                                                <div class="text-[10px] text-slate-500 font-mono mt-0.5">{{ conn.lat.toFixed(2) }}, {{ conn.lon.toFixed(2) }}</div>
                                                            </div>
                                                        </div>
                                                        <span 
                                                            :style="{ color: connectionTypeColor[conn.type] || '#64748b' }"
                                                            class="text-[10px] font-bold font-mono uppercase"
                                                        >
                                                            {{ conn.type }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="flex justify-between items-center border-t border-white/5 pt-2 mt-1">
                                                    <span class="text-xs text-slate-500 font-mono">+ 0 more connections</span>
                                                    <button @click="activeMapView = 'list'" class="text-xs text-[#CBB48A] hover:underline font-mono font-bold">View All</button>
                                                </div>
                                            </div>

                                            <!-- Risk Indicators checklist sidebar card -->
                                            <div class="bg-[#0c0d10] border border-white/5 rounded-xl p-4 flex flex-col gap-2.5 flex-shrink-0">
                                                <h4 class="text-xs text-slate-500 font-bold uppercase tracking-wider mb-0.5">Risk Indicators</h4>
                                                
                                                <div class="space-y-2 text-xs md:text-sm">
                                                    <div class="flex items-center justify-between py-0.5 border-b border-white/5">
                                                        <span class="text-slate-400">Open Admin Ports</span>
                                                        <span class="text-emerald-400 flex items-center gap-1 font-bold">
                                                            0
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/>
                                                            </svg>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between py-0.5 border-b border-white/5">
                                                        <span class="text-slate-400">Self-Hosted Services</span>
                                                        <span class="text-amber-400 flex items-center gap-1 font-bold">
                                                            1
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                            </svg>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between py-0.5 border-b border-white/5">
                                                        <span class="text-slate-400">Anonymization Services</span>
                                                        <span class="text-emerald-400 flex items-center gap-1 font-bold">
                                                            0
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/>
                                                            </svg>
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between py-0.5">
                                                        <span class="text-slate-400">Suspicious Domains</span>
                                                        <span class="text-emerald-400 flex items-center gap-1 font-bold">
                                                            0
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/>
                                                            </svg>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                    </div>

                                    <!-- Footer -->
                                    <div class="flex items-center justify-between px-6 py-3 border-t border-white/5 bg-[#07080a] text-xs text-slate-500 flex-shrink-0">
                                        <div class="flex items-center gap-3">
                                            <span>Last updated: {{ formattedLastSeen }}</span>
                                            <span>•</span>
                                            <span class="flex items-center gap-1 cursor-pointer hover:text-white transition-colors" @click="copyScanId">
                                                Scan ID: SCAN-{{ props.asset.id.toString().substring(0, 8).toUpperCase() }}
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                                </svg>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span>Data Source: Passive DNS, CDN, HTTP Headers</span>
                                            <svg class="w-3.5 h-3.5 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </Teleport>

                        <!-- COMPLIANCE & PRIVACY -->
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 grid grid-cols-1 md:grid-cols-12 gap-6 items-center shadow-lg shadow-black/40">
                            <div class="md:col-span-7 space-y-4">
                                <h5 class="text-base font-black text-slate-400 uppercase tracking-widest font-mono">COMPLIANCE & PRIVACY</h5>
                                <div class="space-y-3 font-mono text-base">
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">GDPR Compliance</span>
                                        <span class="text-rose-500 font-bold uppercase tracking-wider text-xs">Liability Risk</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">License</span>
                                        <span class="text-white font-bold">UNKNOWN</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Data Handling</span>
                                        <span class="text-white font-bold">Not Verified</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Privacy Policy</span>
                                        <span class="text-rose-500 font-bold">Not Found</span>
                                    </div>
                                    <div class="flex justify-between py-1 border-b border-white/5">
                                        <span class="text-slate-500 font-bold">Supply Chain Score</span>
                                        <span class="text-rose-450 font-bold">20/100</span>
                                    </div>
                                </div>
                            </div>
                            <div class="md:col-span-5 relative h-36 bg-black/25 rounded-xl border border-white/5 overflow-hidden flex items-center justify-center">
                                <!-- Grid dotted overlay -->
                                <div class="absolute inset-0 opacity-10 bg-[radial-gradient(rgba(255,255,255,0.15)_1px,transparent_1px)] [background-size:12px_12px]"></div>

                                <!-- Central Lock Container -->
                                <div class="relative z-10 w-11 h-11 rounded-full bg-slate-950 border border-[#CBB48A]/50 flex items-center justify-center text-[#CBB48A] shadow-[0_0_15px_rgba(203,180,138,0.25)] shadow-black/80">
                                    <svg class="w-5 h-5 text-[#CBB48A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                    </svg>
                                </div>

                                <!-- Inner Orbit Ring (Clockwise) -->
                                <div class="absolute w-20 h-20 border border-[#CBB48A]/15 rounded-full animate-spin [animation-duration:15s] flex items-center justify-center">
                                    <!-- A single glowing indicator dot at the top of the inner ring -->
                                    <div class="absolute top-0 w-1.5 h-1.5 bg-[#CBB48A] rounded-full shadow-[0_0_8px_#CBB48A]"></div>
                                </div>

                                <!-- Middle Orbit Ring (Counter-Clockwise) -->
                                <div class="absolute w-28 h-28 border border-dashed border-[#CBB48A]/20 rounded-full animate-spin [animation-duration:22s] [animation-direction:reverse] flex items-center justify-center">
                                    <!-- Tiny gold dots on the middle ring -->
                                    <div class="absolute top-3 right-3 w-1 h-1 bg-[#CBB48A]/80 rounded-full"></div>
                                    <div class="absolute bottom-3 left-3 w-1 h-1 bg-[#CBB48A]/80 rounded-full"></div>
                                    <div class="absolute top-12 left-1 w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                    <div class="absolute bottom-12 right-1 w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                </div>

                                <!-- Outer Orbit Ring (Clockwise) -->
                                <div class="absolute w-36 h-36 border border-double border-[#CBB48A]/10 rounded-full animate-spin [animation-duration:32s] flex items-center justify-center">
                                    <!-- Orbiting Node Dots with tiny text representations -->
                                    <!-- Point 12 (top) -->
                                    <div class="absolute -top-1 flex flex-col items-center">
                                        <div class="w-1.5 h-1.5 bg-[#CBB48A] rounded-full shadow-[0_0_6px_#CBB48A]"></div>
                                        <span class="text-[6px] font-mono font-bold text-[#CBB48A]/75 mt-0.5">12</span>
                                    </div>
                                    <!-- Point 3 (right) -->
                                    <div class="absolute right-0 flex items-center gap-0.5">
                                        <span class="text-[6px] font-mono font-bold text-[#CBB48A]/75">3</span>
                                        <div class="w-1.5 h-1.5 bg-[#CBB48A] rounded-full shadow-[0_0_6px_#CBB48A]"></div>
                                    </div>
                                    <!-- Point 6 (bottom) -->
                                    <div class="absolute -bottom-1 flex flex-col items-center">
                                        <span class="text-[6px] font-mono font-bold text-[#CBB48A]/75 mb-0.5">6</span>
                                        <div class="w-1.5 h-1.5 bg-[#CBB48A] rounded-full shadow-[0_0_6px_#CBB48A]"></div>
                                    </div>
                                    <!-- Point 9 (left) -->
                                    <div class="absolute left-0 flex items-center gap-0.5">
                                        <div class="w-1.5 h-1.5 bg-[#CBB48A] rounded-full shadow-[0_0_6px_#CBB48A]"></div>
                                        <span class="text-[6px] font-mono font-bold text-[#CBB48A]/75">9</span>
                                    </div>
                                    
                                    <!-- Other intermediate numeric markers and dots -->
                                    <div class="absolute top-4 right-8 flex items-center gap-0.5 rotate-[30deg]">
                                        <div class="w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                        <span class="text-[5px] font-mono text-[#CBB48A]/40 -rotate-[30deg]">1</span>
                                    </div>
                                    <div class="absolute top-10 right-3 flex items-center gap-0.5 rotate-[60deg]">
                                        <div class="w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                        <span class="text-[5px] font-mono text-[#CBB48A]/40 -rotate-[60deg]">2</span>
                                    </div>
                                    <div class="absolute bottom-10 right-3 flex items-center gap-0.5 -rotate-[60deg]">
                                        <div class="w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                        <span class="text-[5px] font-mono text-[#CBB48A]/40 rotate-[60deg]">4</span>
                                    </div>
                                    <div class="absolute bottom-4 right-8 flex items-center gap-0.5 -rotate-[30deg]">
                                        <div class="w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                        <span class="text-[5px] font-mono text-[#CBB48A]/40 rotate-[30deg]">5</span>
                                    </div>
                                    <div class="absolute bottom-4 left-8 flex items-center gap-0.5 rotate-[30deg]">
                                        <span class="text-[5px] font-mono text-[#CBB48A]/40 -rotate-[30deg]">7</span>
                                        <div class="w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                    </div>
                                    <div class="absolute bottom-10 left-3 flex items-center gap-0.5 rotate-[60deg]">
                                        <span class="text-[5px] font-mono text-[#CBB48A]/40 -rotate-[60deg]">8</span>
                                        <div class="w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                    </div>
                                    <div class="absolute top-10 left-3 flex items-center gap-0.5 -rotate-[60deg]">
                                        <span class="text-[5px] font-mono text-[#CBB48A]/40 rotate-[60deg]">10</span>
                                        <div class="w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                    </div>
                                    <div class="absolute top-4 left-8 flex items-center gap-0.5 -rotate-[30deg]">
                                        <span class="text-[5px] font-mono text-[#CBB48A]/40 rotate-[30deg]">11</span>
                                        <div class="w-1 h-1 bg-[#CBB48A]/60 rounded-full"></div>
                                    </div>
                                </div>

                                <!-- Crosshairs lines (stationary) -->
                                <div class="absolute w-40 h-px bg-gradient-to-r from-transparent via-[#CBB48A]/15 to-transparent"></div>
                                <div class="absolute h-40 w-px bg-gradient-to-b from-transparent via-[#CBB48A]/15 to-transparent"></div>
                                
                                <!-- Dotted outer scanner circle (glowing/pulsing) -->
                                <div class="absolute w-40 h-40 border border-dashed border-[#CBB48A]/5 rounded-full animate-pulse"></div>
                            </div>
                        </div>
                    </div>

                    </div>

                    <!-- TAB: SITE TOPOLOGY -->
                    <div v-if="activeTab === 'topology'" class="space-y-6 animate-fade-in">
                        <!-- Top Header Row -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-lg font-black text-white uppercase tracking-wider font-mono">SITE TOPOLOGY</h3>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/10 border border-emerald-500/25 text-emerald-400 uppercase tracking-widest font-mono select-none">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        LIVE MONITORING ACTIVE
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 font-semibold leading-relaxed font-sans">Real-time view of discovered assets, connections and dependencies.</p>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <button class="flex items-center gap-2 px-3.5 py-1.5 border border-white/5 bg-white/[0.01] hover:bg-white/[0.04] text-slate-400 hover:text-white transition-all text-xs font-black uppercase tracking-wider rounded-xl cursor-pointer font-mono">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                    </svg>
                                    List View
                                </button>
                                
                                <div class="flex items-center gap-1.5 px-3.5 py-1.5 border border-yellow-500/20 bg-yellow-500/5 text-[#CBB48A] text-xs font-black uppercase tracking-wider rounded-xl font-mono select-none">
                                    <svg class="w-3.5 h-3.5 text-[#CBB48A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94-3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                    </svg>
                                    {{ topologyData.nodes.length }} Nodes
                                </div>
                                
                                <button class="w-9 h-9 flex items-center justify-center border border-white/5 bg-white/[0.01] hover:bg-white/[0.04] text-slate-400 hover:text-white transition-all rounded-xl cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75v4.5m0-4.5h-4.5m4.5 0L15 9m5.25 11.25v-4.5m0 4.5h-4.5m4.5 0L15 15"/>
                                    </svg>
                                </button>
                                
                                <button class="w-9 h-9 flex items-center justify-center border border-white/5 bg-white/[0.01] hover:bg-white/[0.04] text-slate-400 hover:text-white transition-all rounded-xl cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- 3-Column Content Layout -->
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            
                            <!-- Left Column: Node distribution & Health -->
                            <div class="lg:col-span-2 space-y-6">
                                <!-- Node Distribution Card -->
                                <div class="p-4 bg-[#070709] rounded-2xl border border-white/5 space-y-4 shadow-lg shadow-black/40">
                                    <h5 class="text-xs font-black text-slate-500 uppercase tracking-widest font-mono">NODE DISTRIBUTION</h5>
                                    <div class="space-y-3 font-mono text-xs">
                                        <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                            <span class="text-slate-400 flex items-center gap-2">
                                                <component :is="Monitor" class="w-3.5 h-3.5 text-rose-500" />
                                                Web App
                                            </span>
                                            <span class="text-rose-500 font-bold">1</span>
                                        </div>
                                        <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                            <span class="text-slate-400 flex items-center gap-2">
                                                <component :is="Globe" class="w-3.5 h-3.5 text-emerald-400" />
                                                Subdomain
                                            </span>
                                            <span class="text-emerald-400 font-bold">4</span>
                                        </div>
                                        <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                            <span class="text-slate-400 flex items-center gap-2">
                                                <component :is="Cpu" class="w-3.5 h-3.5 text-yellow-400" />
                                                External Service
                                            </span>
                                            <span class="text-yellow-400 font-bold">3</span>
                                        </div>
                                        <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                            <span class="text-slate-400 flex items-center gap-2">
                                                <component :is="Database" class="w-3.5 h-3.5 text-blue-400" />
                                                Database
                                            </span>
                                            <span class="text-blue-400 font-bold">2</span>
                                        </div>
                                        <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                            <span class="text-slate-400 flex items-center gap-2">
                                                <component :is="Shield" class="w-3.5 h-3.5 text-purple-400" />
                                                CDN / Network
                                            </span>
                                            <span class="text-purple-400 font-bold">2</span>
                                        </div>
                                        <div class="flex items-center justify-between pb-1.5 border-b border-white/5">
                                            <span class="text-slate-400 flex items-center gap-2">
                                                <component :is="HardDrive" class="w-3.5 h-3.5 text-cyan-400" />
                                                File / Storage
                                            </span>
                                            <span class="text-cyan-400 font-bold">2</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Network Health Card -->
                                <div class="p-4 bg-[#070709] rounded-2xl border border-white/5 space-y-4 shadow-lg shadow-black/40">
                                    <span class="text-xs font-black text-slate-500 uppercase tracking-widest font-mono">NETWORK HEALTH</span>
                                    <div class="relative h-20 w-full overflow-hidden rounded-lg bg-black/20 border border-white/[0.02]">
                                        <!-- SVG Sparkline with gradient -->
                                        <svg class="w-full h-full" viewBox="0 0 100 40" preserveAspectRatio="none">
                                            <defs>
                                                <linearGradient id="health-glow" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.25" />
                                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0.0" />
                                                </linearGradient>
                                            </defs>
                                            <!-- Filled area -->
                                            <path d="M 0 35 Q 20 20, 40 30 T 80 15 T 100 10 L 100 40 L 0 40 Z" fill="url(#health-glow)" />
                                            <!-- Sparkline path -->
                                            <path d="M 0 35 Q 20 20, 40 30 T 80 15 T 100 10" fill="none" stroke="#10b981" stroke-width="1.5" stroke-linecap="round" />
                                            <!-- Highlight dot -->
                                            <circle cx="100" cy="10" r="2.5" fill="#10b981" />
                                        </svg>
                                        <!-- Pulsing dot at current point -->
                                        <div class="absolute top-[8px] right-[2px] w-2 h-2">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between text-xs font-mono">
                                        <span class="text-emerald-400 font-bold flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Healthy
                                        </span>
                                        <span class="text-slate-400 font-bold">99.8%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Center Column: interactive graph -->
                            <div class="lg:col-span-7 bg-[#070709] rounded-2xl border border-white/5 overflow-hidden h-[620px] relative shadow-lg shadow-black/40">
                                <TopologyGraph :data="topologyData" @node-click="handleTopologyNodeClick" />

                                <!-- Floating Legend overlay at bottom center -->
                                <div class="flex flex-wrap items-center justify-center gap-6 text-[10px] font-mono text-slate-500 absolute bottom-6 left-0 right-0 z-30 pointer-events-none select-none">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-4 h-0.5 bg-rose-500/80 inline-block"></span>
                                        <span>Primary Connection</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-4 h-0.5 bg-slate-500/50 inline-block"></span>
                                        <span>Secondary Connection</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-4 h-0.5 border-t border-dashed border-slate-500/60 inline-block"></span>
                                        <span>Data Flow</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-amber-400 inline-block animate-pulse"></span>
                                        <span>Live Traffic</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Selected node details -->
                            <div class="lg:col-span-3">
                                <div class="p-5 bg-[#070709] rounded-2xl border border-white/5 flex flex-col justify-between h-[620px] shadow-lg shadow-black/40">
                                    <div class="space-y-4">
                                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-widest font-mono">SELECTED ASSET</span>
                                        
                                        <!-- Selected Node Icon & Identity Card -->
                                        <div class="flex items-center gap-4 py-1.5">
                                            <div class="relative w-14 h-14 rounded-full border border-rose-500/30 bg-rose-500/5 flex items-center justify-center text-rose-500 shrink-0">
                                                <!-- Pulsing outer ring -->
                                                <div class="absolute inset-[-3px] rounded-full border border-rose-500/15 animate-pulse"></div>
                                                <component :is="Monitor" class="w-7 h-7 text-rose-500" />
                                            </div>
                                            <div class="flex flex-col min-w-0">
                                                <h4 class="text-base font-black text-white truncate max-w-[170px]" :title="activeTopologyNode.id">{{ activeTopologyNode.id }}</h4>
                                                <span class="text-xs font-bold text-rose-500 font-mono uppercase tracking-wider mt-0.5">{{ activeTopologyNode.type }}</span>
                                                <div class="flex items-center gap-1.5 mt-1">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    <span class="text-[10px] font-black text-emerald-400 uppercase tracking-widest font-mono">Active</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Technical Specs Table -->
                                        <div class="space-y-2.5 font-mono text-xs pt-2">
                                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                                <span class="text-slate-500">IP Address</span>
                                                <span class="text-white">{{ activeTopologyNode.ip }}</span>
                                            </div>
                                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                                <span class="text-slate-500">Status</span>
                                                <span class="text-emerald-400 font-bold">{{ activeTopologyNode.status }}</span>
                                            </div>
                                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                                <span class="text-slate-500">Protocol</span>
                                                <span class="text-white">{{ activeTopologyNode.protocol }}</span>
                                            </div>
                                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                                <span class="text-slate-500">Server</span>
                                                <span class="text-[#CBB48A]">{{ activeTopologyNode.server }}</span>
                                            </div>
                                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                                <span class="text-slate-500">Location</span>
                                                <span class="text-white flex items-center gap-1">
                                                    <span>{{ getCountryFlag(activeTopologyNode.location) }}</span>
                                                    <span>{{ activeTopologyNode.location }}</span>
                                                </span>
                                            </div>
                                            <div class="flex justify-between py-1.5 border-b border-white/5">
                                                <span class="text-slate-500">Last Seen</span>
                                                <span class="text-slate-400">{{ activeTopologyNode.lastSeen }}</span>
                                            </div>
                                        </div>

                                        <!-- Open Ports Section -->
                                        <div class="pt-2 space-y-2.5">
                                            <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest font-mono">OPEN PORTS</span>
                                            <div class="flex flex-wrap gap-x-4 gap-y-2 font-mono text-xs">
                                                <div v-for="port in activeTopologyNode.ports" :key="port.port" class="flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]"></span>
                                                    <span class="text-white font-bold">{{ port.port }}</span>
                                                    <span class="text-slate-550 uppercase text-[10px] font-semibold">{{ port.protocol }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- View Asset Details Full Button -->
                                    <button @click="showTopologyDetailsModal = true" class="w-full py-2.5 border border-white/5 bg-white/[0.01] hover:bg-white/[0.04] hover:border-white/10 text-slate-400 hover:text-white rounded-xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-1.5 font-mono cursor-pointer">
                                        View Asset Details
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- TAB: TECHNOLOGIES -->
                    <div v-if="activeTab === 'technologies'" class="space-y-6 animate-fade-in">
                        <!-- Top Header Row -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="flex flex-col gap-1.5">
                                <h3 class="text-[22px] font-semibold text-white tracking-wide font-sans">TECHNOLOGY ECOSYSTEM</h3>
                                <p class="text-sm text-slate-400 font-sans">Detected technologies and their relationships across the asset.</p>
                            </div>
                            
                            <div class="flex items-center">
                                <button @click="showFullStackModal = true" class="flex items-center gap-2 px-4 py-2 border border-white/10 bg-transparent hover:bg-white/5 text-slate-300 hover:text-white transition-all text-sm rounded-lg cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                    </svg>
                                    View Full Stack
                                </button>
                            </div>
                        </div>

                        <!-- 3-Column Content Layout -->
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                            
                            <!-- Left Column: Categories -->
                            <div class="lg:col-span-3">
                                <div class="p-6 bg-[#0B0C10] rounded-xl border border-white/5 shadow-lg shadow-black/40 h-full flex flex-col">
                                    <h5 class="text-xs font-black text-slate-300 uppercase tracking-widest font-mono mb-6">TECHNOLOGY CATEGORIES</h5>
                                    
                                    <div class="space-y-4 font-sans text-[13px] flex-1">
                                        <!-- Backend -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300 flex items-center gap-3">
                                                <div class="w-7 h-7 rounded bg-rose-500/10 flex items-center justify-center text-rose-500">
                                                    <component :is="Server" class="w-4 h-4" />
                                                </div>
                                                Backend
                                            </span>
                                            <div class="flex items-center gap-3 text-slate-300">
                                                <span>3</span>
                                                <div class="w-2 h-2 rounded-full bg-rose-500"></div>
                                            </div>
                                        </div>
                                        <!-- Frontend -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300 flex items-center gap-3">
                                                <div class="w-7 h-7 rounded bg-emerald-500/10 flex items-center justify-center text-emerald-500">
                                                    <component :is="Monitor" class="w-4 h-4" />
                                                </div>
                                                Frontend
                                            </span>
                                            <div class="flex items-center gap-3 text-slate-300">
                                                <span>4</span>
                                                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                            </div>
                                        </div>
                                        <!-- Infra -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300 flex items-center gap-3">
                                                <div class="w-7 h-7 rounded bg-yellow-500/10 flex items-center justify-center text-yellow-500">
                                                    <component :is="Cloud" class="w-4 h-4" />
                                                </div>
                                                Infrastructure
                                            </span>
                                            <div class="flex items-center gap-3 text-slate-300">
                                                <span>3</span>
                                                <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                            </div>
                                        </div>
                                        <!-- Database -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300 flex items-center gap-3">
                                                <div class="w-7 h-7 rounded bg-teal-500/10 flex items-center justify-center text-teal-500">
                                                    <component :is="Database" class="w-4 h-4" />
                                                </div>
                                                Database
                                            </span>
                                            <div class="flex items-center gap-3 text-slate-300">
                                                <span>2</span>
                                                <div class="w-2 h-2 rounded-full bg-teal-500"></div>
                                            </div>
                                        </div>
                                        <!-- CDN / Network -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300 flex items-center gap-3">
                                                <div class="w-7 h-7 rounded bg-purple-500/10 flex items-center justify-center text-purple-500">
                                                    <component :is="Globe" class="w-4 h-4" />
                                                </div>
                                                CDN / Network
                                            </span>
                                            <div class="flex items-center gap-3 text-slate-300">
                                                <span>2</span>
                                                <div class="w-2 h-2 rounded-full bg-purple-500"></div>
                                            </div>
                                        </div>
                                        <!-- Security -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300 flex items-center gap-3">
                                                <div class="w-7 h-7 rounded bg-blue-500/10 flex items-center justify-center text-blue-500">
                                                    <component :is="Shield" class="w-4 h-4" />
                                                </div>
                                                Security
                                            </span>
                                            <div class="flex items-center gap-3 text-slate-300">
                                                <span>2</span>
                                                <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                            </div>
                                        </div>
                                        <!-- Analytics -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300 flex items-center gap-3">
                                                <div class="w-7 h-7 rounded bg-orange-500/10 flex items-center justify-center text-orange-500">
                                                    <component :is="Activity" class="w-4 h-4" />
                                                </div>
                                                Analytics
                                            </span>
                                            <div class="flex items-center gap-3 text-slate-300">
                                                <span>2</span>
                                                <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                                            </div>
                                        </div>
                                        <!-- Third Party -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-slate-300 flex items-center gap-3">
                                                <div class="w-7 h-7 rounded bg-slate-400/10 flex items-center justify-center text-slate-400">
                                                    <component :is="Boxes" class="w-4 h-4" />
                                                </div>
                                                Third Party
                                            </span>
                                            <div class="flex items-center gap-3 text-slate-300">
                                                <span>6</span>
                                                <div class="w-2 h-2 rounded-full bg-slate-400"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button @click="showFullStackModal = true" class="w-full flex items-center justify-between text-slate-400 hover:text-white mt-8 pt-5 border-t border-white/5 text-sm transition-colors group">
                                        <div class="flex items-center gap-3">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                            </svg>
                                            View All Technologies (24)
                                        </div>
                                        <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Center Column: Spoke Map -->
                            <div class="lg:col-span-6">
                                <div class="p-6 bg-[#0B0C10] rounded-xl border border-white/5 shadow-lg shadow-black/40 relative h-full flex flex-col min-h-[600px]">
                                    
                                    <div class="flex justify-between items-start mb-2 relative z-10">
                                        <h5 class="text-xs font-black text-slate-300 uppercase tracking-widest font-mono">TECHNOLOGY RELATIONSHIP MAP</h5>
                                        <div class="flex items-center gap-3">
                                            <div class="flex items-center gap-4 text-[13px] font-sans border border-white/10 rounded-lg px-4 py-1.5 bg-black/50">
                                                <button class="text-[#CBB48A] font-semibold flex items-center gap-1.5">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                                    </svg>
                                                    Graph View
                                                </button>
                                                <button class="text-slate-400 hover:text-white transition-colors">List View</button>
                                            </div>
                                            <button class="p-2 border border-white/10 rounded-lg hover:bg-white/5 transition-colors">
                                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex-1 flex justify-center items-center relative">
                                        <svg class="w-full h-[450px]" viewBox="0 0 600 450">
                                            <defs>
                                                <radialGradient id="glow-center" cx="50%" cy="50%" r="50%">
                                                    <stop offset="0%" stop-color="#CBB48A" stop-opacity="0.25" />
                                                    <stop offset="100%" stop-color="#CBB48A" stop-opacity="0" />
                                                </radialGradient>
                                            </defs>
                                            
                                            <!-- Center Glow -->
                                            <circle cx="300" cy="225" r="90" fill="url(#glow-center)" />
                                            
                                            <!-- Spoke Lines -->
                                            <g stroke="rgba(255, 255, 255, 0.15)" stroke-width="1.5">
                                                <line x1="300" y1="225" x2="160" y2="100" />
                                                <line x1="300" y1="225" x2="300" y2="70" />
                                                <line x1="300" y1="225" x2="440" y2="100" />
                                                <line x1="300" y1="225" x2="490" y2="225" />
                                                <line x1="300" y1="225" x2="440" y2="350" />
                                                <line x1="300" y1="225" x2="300" y2="380" />
                                                <line x1="300" y1="225" x2="160" y2="350" />
                                                <line x1="300" y1="225" x2="110" y2="225" />
                                                
                                                <!-- Dotted flow lines -->
                                                <line x1="300" y1="225" x2="440" y2="350" stroke-dasharray="4,4" />
                                            </g>

                                            <!-- Data flow dots on lines -->
                                            <g>
                                                <circle r="2.5" fill="#CBB48A" class="opacity-80"><animateMotion dur="2s" repeatCount="indefinite" path="M 300 225 L 300 70" /></circle>
                                                <circle r="2.5" fill="#CBB48A" class="opacity-80"><animateMotion dur="3s" repeatCount="indefinite" path="M 300 225 L 160 100" /></circle>
                                                <circle r="2.5" fill="#CBB48A" class="opacity-80"><animateMotion dur="2.5s" repeatCount="indefinite" path="M 300 225 L 440 100" /></circle>
                                            </g>

                                            <!-- Central Node -->
                                            <g transform="translate(300, 225)">
                                                <circle r="36" fill="#0B0C10" stroke="#CBB48A" stroke-width="2" class="pulse-circle" />
                                                <path d="M-14,0 A14,14 0 0,0 14,0 A14,14 0 0,0 -14,0 M0,-14 L0,14 M-14,0 L14,0" fill="none" stroke="#CBB48A" stroke-width="2" />
                                                <!-- Added globe latitudes -->
                                                <ellipse cx="0" cy="0" rx="7" ry="14" fill="none" stroke="#CBB48A" stroke-width="2" />
                                                <text y="55" text-anchor="middle" fill="#CBB48A" font-size="14" font-sans="true" font-weight="bold">infosoft.poolreno.com</text>
                                            </g>

                                            <!-- Spoke Nodes -->
                                            <!-- Node 1: Laravel -->
                                            <g transform="translate(160, 100)" class="cursor-pointer group/node">
                                                <circle r="24" fill="#0B0C10" stroke="#ef4444" stroke-width="2" />
                                                <text text-anchor="middle" y="5" fill="#ef4444" font-size="14" font-family="monospace" font-weight="bold">Lav</text>
                                                <text y="45" text-anchor="middle" fill="#f8fafc" font-size="13" font-sans="true">Laravel 11.x</text>
                                                <text y="60" text-anchor="middle" fill="#64748b" font-size="11" font-sans="true">Backend Framework</text>
                                            </g>
                                            <!-- Node 2: Cloudflare -->
                                            <g transform="translate(300, 70)" class="cursor-pointer group/node">
                                                <circle r="24" fill="#0B0C10" stroke="#f97316" stroke-width="2" />
                                                <path d="M-6,2 Q-10,2 -10,-2 Q-10,-5 -6,-5 Q-5,-10 0,-10 Q5,-10 6,-5 Q10,-5 10,-2 Q10,2 6,2 Z" fill="none" stroke="#f97316" stroke-width="2" transform="scale(1.2)" />
                                                <text y="45" text-anchor="middle" fill="#f8fafc" font-size="13" font-sans="true">Cloudflare</text>
                                                <text y="60" text-anchor="middle" fill="#64748b" font-size="11" font-sans="true">CDN / Security</text>
                                            </g>
                                            <!-- Node 3: Vue.js -->
                                            <g transform="translate(440, 100)" class="cursor-pointer group/node">
                                                <circle r="24" fill="#0B0C10" stroke="#10b981" stroke-width="2" />
                                                <text text-anchor="middle" y="5" fill="#10b981" font-size="14" font-family="monospace" font-weight="bold">V</text>
                                                <text y="45" text-anchor="middle" fill="#f8fafc" font-size="13" font-sans="true">Vue.js 3.x</text>
                                                <text y="60" text-anchor="middle" fill="#64748b" font-size="11" font-sans="true">Frontend Framework</text>
                                            </g>
                                            <!-- Node 4: Tailwind CSS -->
                                            <g transform="translate(490, 225)" class="cursor-pointer group/node">
                                                <circle r="24" fill="#0B0C10" stroke="#0ea5e9" stroke-width="2" />
                                                <path d="M-8,0 Q-4,-6 0,0 T8,0" fill="none" stroke="#0ea5e9" stroke-width="2.5" />
                                                <path d="M-8,5 Q-4,-1 0,5 T8,5" fill="none" stroke="#0ea5e9" stroke-width="2.5" />
                                                <text y="45" text-anchor="middle" fill="#f8fafc" font-size="13" font-sans="true">Tailwind CSS</text>
                                                <text y="60" text-anchor="middle" fill="#64748b" font-size="11" font-sans="true">CSS Framework</text>
                                            </g>
                                            <!-- Node 5: Google Analytics -->
                                            <g transform="translate(440, 350)" class="cursor-pointer group/node">
                                                <circle r="24" fill="#0B0C10" stroke="#f59e0b" stroke-width="2" />
                                                <rect x="-8" y="-2" width="4" height="10" fill="#f59e0b" />
                                                <rect x="-2" y="-6" width="4" height="14" fill="#f59e0b" />
                                                <rect x="4" y="-10" width="4" height="18" fill="#f59e0b" />
                                                <text y="45" text-anchor="middle" fill="#f8fafc" font-size="13" font-sans="true">Google Analytics</text>
                                                <text y="60" text-anchor="middle" fill="#64748b" font-size="11" font-sans="true">Analytics</text>
                                            </g>
                                            <!-- Node 6: Nginx -->
                                            <g transform="translate(300, 380)" class="cursor-pointer group/node">
                                                <circle r="24" fill="#0B0C10" stroke="#10b981" stroke-width="2" />
                                                <text text-anchor="middle" y="5" fill="#10b981" font-size="15" font-family="monospace" font-weight="bold">N</text>
                                                <text y="45" text-anchor="middle" fill="#f8fafc" font-size="13" font-sans="true">Nginx</text>
                                                <text y="60" text-anchor="middle" fill="#64748b" font-size="11" font-sans="true">Web Server</text>
                                            </g>
                                            <!-- Node 7: MySQL -->
                                            <g transform="translate(160, 350)" class="cursor-pointer group/node">
                                                <circle r="24" fill="#0B0C10" stroke="#14b8a6" stroke-width="2" />
                                                <ellipse cx="0" cy="-4" rx="8" ry="3" fill="none" stroke="#14b8a6" stroke-width="1.5" />
                                                <path d="M-8,-4 L-8,4 A8,3 0 0,0 8,4 L8,-4" fill="none" stroke="#14b8a6" stroke-width="1.5" />
                                                <path d="M-8,0 L-8,8 A8,3 0 0,0 8,8 L8,0" fill="none" stroke="#14b8a6" stroke-width="1.5" />
                                                <text y="45" text-anchor="middle" fill="#f8fafc" font-size="13" font-sans="true">MySQL 8.0</text>
                                                <text y="60" text-anchor="middle" fill="#64748b" font-size="11" font-sans="true">Database</text>
                                            </g>
                                            <!-- Node 8: PHP -->
                                            <g transform="translate(110, 225)" class="cursor-pointer group/node">
                                                <ellipse cx="0" cy="0" rx="26" ry="16" fill="#0B0C10" stroke="#8b5cf6" stroke-width="2" />
                                                <text text-anchor="middle" y="4" fill="#8b5cf6" font-size="12" font-family="monospace" font-weight="bold">php</text>
                                                <text y="45" text-anchor="middle" fill="#f8fafc" font-size="13" font-sans="true">PHP 8.2</text>
                                                <text y="60" text-anchor="middle" fill="#64748b" font-size="11" font-sans="true">Language</text>
                                            </g>
                                        </svg>
                                    </div>
                                    <div class="absolute bottom-6 left-0 right-0 flex items-center justify-center gap-8 text-[11px] font-sans text-slate-400">
                                        <div class="flex items-center gap-2">
                                            <span class="w-8 h-0.5 bg-[#CBB48A] inline-block"></span>
                                            <span>Primary Technology</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="w-8 h-0.5 bg-slate-500 inline-block"></span>
                                            <span>Supporting Technology</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="w-8 h-0.5 border-t border-dashed border-slate-500 inline-block"></span>
                                            <span>Data Flow</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Stats and Summary -->
                            <div class="lg:col-span-3 space-y-6 flex flex-col h-full">
                                <!-- Tech Stack Summary -->
                                <div class="p-6 bg-[#0B0C10] rounded-xl border border-white/5 shadow-lg shadow-black/40">
                                    <h5 class="text-xs font-black text-slate-300 uppercase tracking-widest font-mono mb-4">TECH STACK SUMMARY</h5>
                                    <div class="flex items-end gap-2 mb-6">
                                        <div class="text-[32px] font-bold text-[#CBB48A] leading-none">24</div>
                                        <div class="text-sm text-slate-400 pb-1">Technologies Detected</div>
                                    </div>
                                    <div class="space-y-3.5 text-[13px] font-sans">
                                        <div class="flex items-center justify-between text-slate-300">
                                            <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-blue-500"></div>Custom / Proprietary</div>
                                            <span>6</span>
                                        </div>
                                        <div class="flex items-center justify-between text-slate-300">
                                            <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>Open Source</div>
                                            <span>13</span>
                                        </div>
                                        <div class="flex items-center justify-between text-slate-300">
                                            <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div>Third Party Services</div>
                                            <span>5</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Technology Maturity -->
                                <div class="p-6 bg-[#0B0C10] rounded-xl border border-white/5 shadow-lg shadow-black/40 flex-1">
                                    <h5 class="text-xs font-black text-slate-300 uppercase tracking-widest font-mono mb-6">TECHNOLOGY MATURITY</h5>
                                    <div class="flex items-center gap-6 h-[120px]">
                                        <!-- Simple Donut Chart Representation using SVG -->
                                        <div class="relative w-[110px] h-[110px] flex-shrink-0">
                                            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                                                <circle cx="18" cy="18" r="15.915" fill="none" stroke="#ef4444" stroke-width="3"></circle>
                                                <circle cx="18" cy="18" r="15.915" fill="none" stroke="#eab308" stroke-width="3" stroke-dasharray="80 100" stroke-dashoffset="-8"></circle>
                                                <circle cx="18" cy="18" r="15.915" fill="none" stroke="#10b981" stroke-width="3" stroke-dasharray="65 100" stroke-dashoffset="-22"></circle>
                                            </svg>
                                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                                <span class="text-xl font-bold text-white">78%</span>
                                                <span class="text-[9px] text-slate-400 mt-0.5">Modern Stack</span>
                                            </div>
                                        </div>
                                        <div class="space-y-4 flex-1 text-[13px] font-sans">
                                            <div class="flex items-center justify-between text-slate-300">
                                                <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>Modern</div>
                                                <span>78%</span>
                                            </div>
                                            <div class="flex items-center justify-between text-slate-300">
                                                <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-yellow-500"></div>Legacy</div>
                                                <span>14%</span>
                                            </div>
                                            <div class="flex items-center justify-between text-slate-300">
                                                <div class="flex items-center gap-3"><div class="w-2.5 h-2.5 rounded-full bg-rose-500"></div>Outdated</div>
                                                <span>8%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stack Health Score -->
                                <div class="p-6 bg-[#0B0C10] rounded-xl border border-white/5 shadow-lg shadow-black/40">
                                    <div class="flex justify-between items-end mb-4">
                                        <h5 class="text-xs font-black text-slate-300 uppercase tracking-widest font-mono">STACK HEALTH SCORE</h5>
                                        <div class="text-right">
                                            <div class="text-[28px] font-bold text-white leading-none">84<span class="text-base text-slate-500">/100</span></div>
                                            <div class="text-emerald-500 text-[13px] font-medium mt-1">Healthy</div>
                                        </div>
                                    </div>
                                    <div class="w-full bg-slate-800/50 h-[6px] rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: 84%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: EVIDENCE -->
                    <div v-if="activeTab === 'evidence'" class="space-y-6 animate-fade-in">
                        <!-- Header Search & Filters -->
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-white/5 pb-4">
                            <div>
                                <h4 class="text-lg font-black text-white uppercase tracking-widest font-mono">EVIDENCE OVERVIEW</h4>
                                <p class="text-base text-slate-500 font-medium font-sans mt-0.5">Raw forensic evidence collected during the scan. All times shown in UTC.</p>
                            </div>
                            
                            <!-- Search -->
                            <div class="flex items-center gap-3 w-full md:w-auto">
                                <div class="relative flex-1 md:w-64">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    </span>
                                    <input 
                                        type="text" 
                                        placeholder="Search evidence..." 
                                        v-model="evidenceSearchQuery"
                                        class="block w-full pl-9 pr-3 py-1.5 bg-[#0B0C10] border border-white/5 rounded-lg text-sm text-gray-300 placeholder-slate-500 focus:outline-none focus:border-[#CBB48A]/40 focus:ring-0 transition-all font-sans"
                                    />
                                </div>
                                <button class="p-2 bg-white/[0.02] border border-white/5 rounded-lg text-slate-400 hover:text-white transition-all cursor-pointer">
                                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Filter Pill buttons -->
                        <div class="flex flex-wrap gap-2">
                            <button @click="activeEvidenceTab = 'all'" :class="[activeEvidenceTab === 'all' ? 'border-[#CBB48A] text-[#CBB48A]' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:text-white', 'px-3 py-1 border rounded-lg text-xs font-medium transition-all font-sans flex items-center gap-2 cursor-pointer']">All Evidence <span :class="[activeEvidenceTab === 'all' ? 'text-[#CBB48A]' : 'text-[#CBB48A]', 'font-bold']">64</span></button>
                            <button @click="activeEvidenceTab = 'network'" :class="[activeEvidenceTab === 'network' ? 'border-[#CBB48A] text-[#CBB48A]' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:text-white', 'px-3 py-1 border rounded-lg text-xs font-medium transition-all font-sans flex items-center gap-2 cursor-pointer']">Network <span class="text-slate-300">14</span></button>
                            <button @click="activeEvidenceTab = 'http'" :class="[activeEvidenceTab === 'http' ? 'border-[#CBB48A] text-[#CBB48A]' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:text-white', 'px-3 py-1 border rounded-lg text-xs font-medium transition-all font-sans flex items-center gap-2 cursor-pointer']">HTTP <span class="text-slate-300">18</span></button>
                            <button @click="activeEvidenceTab = 'code'" :class="[activeEvidenceTab === 'code' ? 'border-[#CBB48A] text-[#CBB48A]' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:text-white', 'px-3 py-1 border rounded-lg text-xs font-medium transition-all font-sans flex items-center gap-2 cursor-pointer']">Code <span class="text-slate-300">12</span></button>
                            <button @click="activeEvidenceTab = 'config'" :class="[activeEvidenceTab === 'config' ? 'border-[#CBB48A] text-[#CBB48A]' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:text-white', 'px-3 py-1 border rounded-lg text-xs font-medium transition-all font-sans flex items-center gap-2 cursor-pointer']">Configuration <span class="text-slate-300">10</span></button>
                            <button @click="activeEvidenceTab = 'file'" :class="[activeEvidenceTab === 'file' ? 'border-[#CBB48A] text-[#CBB48A]' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:text-white', 'px-3 py-1 border rounded-lg text-xs font-medium transition-all font-sans flex items-center gap-2 cursor-pointer']">File & Data <span class="text-slate-300">6</span></button>
                            <button @click="activeEvidenceTab = 'other'" :class="[activeEvidenceTab === 'other' ? 'border-[#CBB48A] text-[#CBB48A]' : 'border-white/5 bg-white/[0.02] text-slate-400 hover:text-white', 'px-3 py-1 border rounded-lg text-xs font-medium transition-all font-sans flex items-center gap-2 cursor-pointer']">Other <span class="text-slate-300">4</span></button>
                            <button class="px-2 py-1 border border-white/5 bg-white/[0.02] text-slate-400 hover:text-white rounded-lg transition-all cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                            </button>
                        </div>

                        <!-- Dynamic Content of Evidence Tab -->
                        <div v-if="activeEvidenceTab === 'all' && !evidenceSearchQuery" class="space-y-6">
                            <!-- Row 1: HTTP, Network, Code/File Lists -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                
                                <!-- HTTP Evidence -->
                                <div class="p-5 bg-[#0B0C10] rounded-xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-bold text-slate-300 font-sans flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-md bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                            </div>
                                            HTTP Evidence (18)
                                        </h5>
                                        <div class="space-y-2.5 font-sans text-xs">
                                            <div v-for="item in evidenceList.filter(e => e.category === 'http').slice(0, 5)" :key="item.id" class="flex items-center gap-3">
                                                <span :class="[item.status === 'error' ? 'bg-red-500' : item.status === 'warning' ? 'bg-amber-500' : 'bg-emerald-500', 'w-1.5 h-1.5 rounded-full shrink-0']"></span>
                                                <span class="text-white w-28 truncate font-bold font-mono">{{ item.name }}</span>
                                                <span class="text-slate-400 w-16">{{ item.value }}</span>
                                                <span class="text-blue-400 bg-blue-950/40 border border-blue-500/20 px-1.5 py-0.5 rounded text-[10px]">{{ item.type }}</span>
                                                <span class="text-slate-500 ml-auto whitespace-nowrap">{{ item.time }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="showEvidenceModal = 'HTTP'" class="w-fit mt-5 text-xs font-medium text-[#CBB48A] hover:text-white flex items-center gap-1 transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        View all 18 HTTP evidence &rarr;
                                    </button>
                                </div>

                                <!-- Network Evidence -->
                                <div class="p-5 bg-[#0B0C10] rounded-xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-bold text-slate-300 font-sans flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-md bg-purple-500/10 border border-purple-500/20 flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                            </div>
                                            Network Evidence (14)
                                        </h5>
                                        <div class="space-y-2.5 font-sans text-xs">
                                            <div v-for="item in evidenceList.filter(e => e.category === 'network').slice(0, 5)" :key="item.id" class="flex items-center gap-3">
                                                <span :class="[item.status === 'error' ? 'bg-red-500' : item.status === 'warning' ? 'bg-amber-500' : 'bg-emerald-500', 'w-1.5 h-1.5 rounded-full shrink-0']"></span>
                                                <span class="text-white w-32 truncate font-bold font-mono">{{ item.name }}</span>
                                                <span class="text-slate-400 truncate w-32">{{ item.value }}</span>
                                                <span class="text-yellow-500 bg-yellow-950/40 border border-yellow-500/20 px-1.5 py-0.5 rounded text-[10px] ml-auto">{{ item.type }}</span>
                                                <span class="text-slate-500 whitespace-nowrap">{{ item.time }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="showEvidenceModal = 'Network'" class="w-fit mt-5 text-xs font-medium text-[#CBB48A] hover:text-white flex items-center gap-1 transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        View all 14 network evidence &rarr;
                                    </button>
                                </div>

                                <!-- Code & File Evidence -->
                                <div class="p-5 bg-[#0B0C10] rounded-xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-bold text-slate-300 font-sans flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-md bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                            </div>
                                            Code & File Evidence (12)
                                        </h5>
                                        <div class="space-y-2.5 font-sans text-xs">
                                            <div v-for="item in evidenceList.filter(e => e.category === 'code').slice(0, 5)" :key="item.id" class="flex items-center gap-3">
                                                <span :class="[item.status === 'error' ? 'bg-red-500' : item.status === 'warning' ? 'bg-amber-500' : 'bg-emerald-500', 'w-1.5 h-1.5 rounded-full shrink-0']"></span>
                                                <span class="text-white w-32 truncate font-bold font-mono">{{ item.name }}</span>
                                                <span class="text-slate-400">{{ item.value }}</span>
                                                <span class="text-yellow-500 bg-yellow-950/40 border border-yellow-500/20 px-1.5 py-0.5 rounded text-[10px] ml-auto">{{ item.type }}</span>
                                                <span class="text-slate-500 whitespace-nowrap">{{ item.time }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="showEvidenceModal = 'Code'" class="w-fit mt-5 text-xs font-medium text-[#CBB48A] hover:text-white flex items-center gap-1 transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        View all 12 file evidence &rarr;
                                    </button>
                                </div>

                            </div>

                            <!-- Row 2: Response Header Editor, Technology Fingerprints, Passive DNS History -->
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                
                                <!-- Response Header Evidence -->
                                <div class="p-5 bg-[#0B0C10] rounded-xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                                    <div class="space-y-4">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                            <h5 class="text-sm font-bold text-white font-sans">Response Header Evidence</h5>
                                        </div>
                                        <!-- Stylized Code Editor -->
                                        <div class="relative bg-transparent text-xs text-slate-400 overflow-x-auto select-all leading-relaxed font-mono">
                                            <div class="flex gap-4">
                                                <div class="text-slate-600 select-none text-right">
                                                    1<br>2<br>3<br>4<br>5<br>6<br>7<br>8
                                                </div>
                                                <div>
                                                    HTTP/1.1 200 OK<br>
                                                    Server: nginx<br>
                                                    Date: Sun, 22 Jun 2026 16:54:21 GMT<br>
                                                    Content-Type: text/html; charset=UTF-8<br>
                                                    X-Powered-By: PHP/8.2.12<br>
                                                    Set-Cookie: PHPSESSID=***; path=/; secure; HttpOnly<br>
                                                    Cache-Control: no-cache, private<br>
                                                    X-Frame-Options: SAMEORIGIN
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] font-sans text-slate-500 mt-4 pt-4 border-t border-white/5">
                                        <span>Captured from: <span class="text-slate-400">https://infosoft.poolreno.com/</span></span>
                                        <span>Jun 22, 2026 16:54:21</span>
                                    </div>
                                </div>

                                <!-- Technology Fingerprints -->
                                <div class="p-5 bg-[#0B0C10] rounded-xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                                    <div class="space-y-4">
                                        <h5 class="text-sm font-bold text-slate-300 font-sans flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-md bg-white/5 border border-white/10 flex items-center justify-center">
                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                                            </div>
                                            Technology Fingerprints
                                        </h5>
                                        <div class="space-y-3 font-sans text-xs">
                                            <div class="flex items-center gap-3">
                                                <div class="w-6 h-6 rounded-full border border-red-500/20 bg-red-500/10 flex items-center justify-center text-[10px] text-red-500 font-bold font-mono">L</div>
                                                <span class="text-white w-24 font-bold">Laravel</span>
                                                <span class="text-slate-400">11.x</span>
                                                <span class="text-red-400 bg-red-950/40 border border-red-500/20 px-1.5 py-0.5 rounded text-[10px] ml-auto">Backend</span>
                                                <span class="text-slate-500 whitespace-nowrap">High Confidence</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="w-6 h-6 rounded-full border border-orange-500/20 bg-orange-500/10 flex items-center justify-center text-[10px] text-orange-500 font-bold font-mono">W</div>
                                                <span class="text-white w-24 font-bold">WordPress</span>
                                                <span class="text-slate-400">6.4.x</span>
                                                <span class="text-purple-400 bg-purple-950/40 border border-purple-500/20 px-1.5 py-0.5 rounded text-[10px] ml-auto">CMS</span>
                                                <span class="text-slate-500 whitespace-nowrap">High Confidence</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="w-6 h-6 rounded-full border border-emerald-500/20 bg-emerald-500/10 flex items-center justify-center text-[10px] text-emerald-500 font-bold font-mono">V</div>
                                                <span class="text-white w-24 font-bold">Vue.js</span>
                                                <span class="text-slate-400">3.3.x</span>
                                                <span class="text-indigo-400 bg-indigo-950/40 border border-indigo-500/20 px-1.5 py-0.5 rounded text-[10px] ml-auto">Frontend</span>
                                                <span class="text-slate-500 whitespace-nowrap">High Confidence</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="w-6 h-6 rounded-full border border-cyan-500/20 bg-cyan-500/10 flex items-center justify-center text-[10px] text-cyan-500 font-bold font-mono">~</div>
                                                <span class="text-white w-24 font-bold">Tailwind CSS</span>
                                                <span class="text-slate-400">3.x</span>
                                                <span class="text-pink-400 bg-pink-950/40 border border-pink-500/20 px-1.5 py-0.5 rounded text-[10px] ml-auto">CSS Framework</span>
                                                <span class="text-slate-500 whitespace-nowrap">High Confidence</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <div class="w-6 h-6 rounded-full border border-indigo-500/20 bg-indigo-500/10 flex items-center justify-center text-[10px] text-indigo-500 font-bold font-mono">P</div>
                                                <span class="text-white w-24 font-bold">PHP</span>
                                                <span class="text-slate-400">8.2.x</span>
                                                <span class="text-indigo-400 bg-indigo-950/40 border border-indigo-500/20 px-1.5 py-0.5 rounded text-[10px] ml-auto">Language</span>
                                                <span class="text-slate-500 whitespace-nowrap">High Confidence</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="showEvidenceModal = 'Fingerprints'" class="w-fit mt-5 text-xs font-medium text-[#CBB48A] hover:text-white flex items-center gap-1 transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        View all fingerprints &rarr;
                                    </button>
                                </div>

                                <!-- Passive DNS History -->
                                <div class="p-5 bg-[#0B0C10] rounded-xl border border-white/5 flex flex-col justify-between shadow-lg shadow-black/40">
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-sm font-bold text-slate-300 font-sans flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-blue-500/10 border border-blue-500/20 flex items-center justify-center">
                                                    <svg class="w-3.5 h-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                                </div>
                                                Passive DNS History
                                            </h5>
                                            <button @click="showEvidenceModal = 'DNS History'" class="border border-white/10 bg-white/5 px-2 py-1.5 rounded-lg text-xs font-medium text-slate-300 hover:text-white flex items-center gap-1.5 cursor-pointer font-sans transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                                View Full History
                                            </button>
                                        </div>
                                        <div class="space-y-3 font-sans text-xs">
                                            <div class="flex items-center gap-3">
                                                <span class="text-slate-400 w-44 truncate font-mono">infosoft.poolreno.com</span>
                                                <span class="text-slate-500 w-4 font-bold">A</span>
                                                <span class="text-slate-300 ml-auto font-mono">103.21.244.0</span>
                                                <span class="text-slate-500 whitespace-nowrap">Jun 22, 2026</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-slate-400 w-44 truncate font-mono">infosoft.poolreno.com</span>
                                                <span class="text-slate-500 w-4 font-bold">A</span>
                                                <span class="text-slate-300 ml-auto font-mono">172.67.138.45</span>
                                                <span class="text-slate-500 whitespace-nowrap">Jun 20, 2026</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-slate-400 w-44 truncate font-mono">infosoft.poolreno.com</span>
                                                <span class="text-slate-500 w-4 font-bold">A</span>
                                                <span class="text-slate-300 ml-auto font-mono">104.21.80.1</span>
                                                <span class="text-slate-500 whitespace-nowrap">Jun 18, 2026</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span class="text-slate-400 w-44 truncate font-mono">infosoft.poolreno.com</span>
                                                <span class="text-slate-500 w-4 font-bold">A</span>
                                                <span class="text-slate-300 ml-auto font-mono">104.21.32.1</span>
                                                <span class="text-slate-500 whitespace-nowrap">Jun 15, 2026</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click="showEvidenceModal = 'DNS'" class="w-fit mt-5 text-xs font-medium text-[#CBB48A] hover:text-white flex items-center gap-1 transition-colors cursor-pointer">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        View all DNS history &rarr;
                                    </button>
                                </div>

                            </div>
                        </div>

                        <!-- Filtered List View -->
                        <div v-else class="p-6 bg-[#0B0C10] rounded-xl border border-white/5 shadow-lg shadow-black/40 space-y-4 animate-fade-in font-sans text-base">
                            <div class="flex justify-between items-center">
                                <h5 class="text-xs font-black text-slate-450 uppercase tracking-widest font-mono">
                                    Filtered Evidence Results ({{ filteredEvidence.length }})
                                </h5>
                                <button @click="resetEvidenceFilters" class="text-xs font-black text-[#CBB48A] hover:underline cursor-pointer uppercase tracking-wider font-mono">
                                    Clear Filters
                                </button>
                            </div>
                            
                            <div class="divide-y divide-white/5 max-h-[600px] overflow-y-auto custom-scrollbar pr-2">
                                <div v-for="item in filteredEvidence" :key="item.id" class="flex items-center gap-3 py-3 hover:bg-white/[0.01] px-2 rounded-lg transition-colors text-xs font-sans">
                                    <span :class="[item.status === 'error' ? 'bg-red-500' : item.status === 'warning' ? 'bg-amber-500' : 'bg-emerald-500', 'w-1.5 h-1.5 rounded-full shrink-0']"></span>
                                    <span class="text-white font-bold w-1/3 truncate font-mono">{{ item.name }}</span>
                                    <span class="text-slate-400 w-1/3 truncate">{{ item.value }}</span>
                                    <span :class="[
                                        item.category === 'http' ? 'text-blue-400 bg-blue-950/40 border border-blue-500/20' :
                                        item.category === 'network' ? 'text-purple-400 bg-purple-950/40 border border-purple-500/20' :
                                        item.category === 'code' ? 'text-emerald-400 bg-emerald-950/40 border border-emerald-500/20' :
                                        item.category === 'config' ? 'text-yellow-400 bg-yellow-950/40 border border-yellow-500/20' :
                                        item.category === 'file' ? 'text-orange-450 bg-orange-950/40 border border-orange-500/20' :
                                        'text-slate-400 bg-slate-900/40 border border-slate-700/20',
                                        'px-1.5 py-0.5 rounded text-[10px] uppercase font-mono'
                                    ]">{{ item.type }}</span>
                                    <span class="text-slate-500 ml-auto whitespace-nowrap font-mono">{{ item.time }}</span>
                                </div>
                                <div v-if="filteredEvidence.length === 0" class="py-12 text-center text-slate-500 font-sans">
                                    No evidence matches your filter or search query.
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- TAB: RECOMMENDATIONS -->
                    <div v-if="activeTab === 'recommendations'" class="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-fade-in items-stretch">

                        <!-- Main Roadmap List (left, stretches to right panel height) -->
                        <div class="lg:col-span-8 bg-[#0b0c0f] rounded-2xl border border-white/5 shadow-lg shadow-black/40 flex flex-col overflow-hidden">
                            <!-- Header -->
                            <div class="px-6 pt-6 pb-4">
                                <h4 class="text-base font-black text-white uppercase tracking-widest font-mono">RECOMMENDATION ROADMAP</h4>
                                <p class="text-sm text-slate-500 font-medium font-sans mt-0.5">Prioritized security actions to reduce risk, improve resilience, and increase your LUME score.</p>
                            </div>

                            <!-- Filter pills -->
                            <div class="px-6 pb-4 flex flex-wrap items-center gap-3 font-sans text-sm">
                                <button
                                    @click="activeRecFilter = 'all'"
                                    :class="[activeRecFilter === 'all' ? 'bg-white/10 text-white font-bold' : 'text-slate-400 hover:text-white', 'px-3 py-1 rounded-full text-xs font-medium transition-all cursor-pointer border border-white/5']"
                                >All ({{ recommendationsList.length }})</button>

                                <button
                                    @click="activeRecFilter = 'critical'"
                                    :class="[activeRecFilter === 'critical' ? 'text-white font-bold' : 'text-slate-400 hover:text-white', 'flex items-center gap-1.5 text-xs font-medium transition-all cursor-pointer']"
                                >
                                    <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                                    Critical ({{ recommendationsList.filter((r: any) => r.severity === 'CRITICAL').length }})
                                </button>
                                <button
                                    @click="activeRecFilter = 'high'"
                                    :class="[activeRecFilter === 'high' ? 'text-white font-bold' : 'text-slate-400 hover:text-white', 'flex items-center gap-1.5 text-xs font-medium transition-all cursor-pointer']"
                                >
                                    <span class="w-2 h-2 rounded-full bg-orange-400 shrink-0"></span>
                                    High ({{ recommendationsList.filter((r: any) => r.severity === 'HIGH').length }})
                                </button>
                                <button
                                    @click="activeRecFilter = 'medium'"
                                    :class="[activeRecFilter === 'medium' ? 'text-white font-bold' : 'text-slate-400 hover:text-white', 'flex items-center gap-1.5 text-xs font-medium transition-all cursor-pointer']"
                                >
                                    <span class="w-2 h-2 rounded-full bg-yellow-400 shrink-0"></span>
                                    Medium ({{ recommendationsList.filter((r: any) => r.severity === 'MEDIUM').length }})
                                </button>
                                <button
                                    @click="activeRecFilter = 'low'"
                                    :class="[activeRecFilter === 'low' ? 'text-white font-bold' : 'text-slate-400 hover:text-white', 'flex items-center gap-1.5 text-xs font-medium transition-all cursor-pointer']"
                                >
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                    Low ({{ recommendationsList.filter((r: any) => r.severity === 'LOW').length }})
                                </button>
                            </div>

                            <!-- Scrollable recommendation rows -->
                            <div class="flex-1 overflow-y-auto divide-y divide-white/5 font-sans" style="scrollbar-width:thin;scrollbar-color:rgba(255,255,255,0.08) transparent;min-height:0;">
                                <div
                                    v-for="item in filteredRecommendations"
                                    :key="item.id"
                                    class="flex items-start gap-4 px-6 py-5 hover:bg-white/[0.02] transition-all cursor-pointer"
                                    @click="toggleRecommendation(item.id)"
                                >
                                    <!-- Number badge (square, severity-colored border) -->
                                    <div
                                        :class="[
                                            item.severity === 'CRITICAL' ? 'border-rose-500/40 bg-rose-500/10 text-rose-400' :
                                            item.severity === 'HIGH'     ? 'border-orange-500/40 bg-orange-500/10 text-orange-400' :
                                            item.severity === 'MEDIUM'   ? 'border-yellow-400/40 bg-yellow-400/10 text-yellow-400' :
                                                                           'border-emerald-400/40 bg-emerald-400/10 text-emerald-400',
                                            'w-10 h-10 rounded-lg border-2 font-mono font-black text-base flex items-center justify-center shrink-0'
                                        ]"
                                    >{{ item.id }}</div>

                                    <!-- Content: badge + title + description + why this matters -->
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span
                                                :class="[
                                                    item.severity === 'CRITICAL' ? 'bg-rose-500 text-white' :
                                                    item.severity === 'HIGH'     ? 'bg-orange-500 text-white' :
                                                    item.severity === 'MEDIUM'   ? 'bg-yellow-400 text-slate-900' :
                                                                                   'bg-emerald-500 text-slate-900',
                                                    'text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded font-mono'
                                                ]"
                                            >{{ item.severity }}</span>
                                            <span class="text-white font-bold text-sm leading-snug">{{ item.title }}</span>
                                        </div>
                                        <p class="text-xs text-slate-400 leading-relaxed mb-2">{{ item.description }}</p>

                                        <!-- Expandable why section -->
                                        <div v-if="expandedRecommendations[item.id]" class="mb-2 p-3 bg-[#CBB48A]/5 border border-[#CBB48A]/15 rounded-lg">
                                            <p class="text-xs text-[#CBB48A]/80 leading-relaxed">{{ item.why }}</p>
                                        </div>

                                        <button class="text-xs text-[#CBB48A] font-semibold hover:underline flex items-center gap-1 transition-all" @click.stop="toggleRecommendation(item.id)">
                                            {{ expandedRecommendations[item.id] ? 'Hide details ↑' : 'Why this matters →' }}
                                        </button>
                                    </div>

                                    <!-- Right stats: IMPACT / DIFFICULTY / TIME ESTIMATE -->
                                    <div class="flex items-start gap-6 shrink-0 font-mono text-xs pt-0.5">
                                        <!-- IMPACT -->
                                        <div class="flex flex-col items-center gap-0.5 min-w-[56px]">
                                            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-sans">IMPACT</span>
                                            <span
                                                :class="[
                                                    item.severity === 'CRITICAL' ? 'text-rose-400' :
                                                    item.severity === 'HIGH'     ? 'text-orange-400' :
                                                    item.severity === 'MEDIUM'   ? 'text-yellow-400' : 'text-emerald-400',
                                                    'text-xl font-black font-mono'
                                                ]"
                                            >+{{ item.impact }}</span>
                                            <span class="text-[10px] text-slate-600 font-sans">Security Score</span>
                                        </div>

                                        <!-- DIFFICULTY -->
                                        <div class="flex flex-col items-center gap-0.5 min-w-[56px]">
                                            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-sans">DIFFICULTY</span>
                                            <span
                                                :class="[
                                                    item.difficulty === 'Low'    ? 'text-emerald-400' :
                                                    item.difficulty === 'Medium' ? 'text-yellow-400' : 'text-rose-400',
                                                    'text-sm font-bold font-sans'
                                                ]"
                                            >{{ item.difficulty }}</span>
                                            <div class="flex gap-1 mt-0.5">
                                                <span v-for="dot in 3" :key="dot" :class="[difficultyClass(item.difficulty, dot), 'w-2 h-2 rounded-full']"></span>
                                            </div>
                                        </div>

                                        <!-- TIME ESTIMATE -->
                                        <div class="flex flex-col items-center gap-0.5 min-w-[72px]">
                                            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-sans">TIME ESTIMATE</span>
                                            <div class="flex items-center gap-1 text-slate-300">
                                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2"/>
                                                </svg>
                                                <span class="text-sm font-bold font-sans text-slate-200">{{ item.time }}</span>
                                            </div>
                                        </div>

                                        <!-- Chevron -->
                                        <div class="flex items-center self-center">
                                            <svg :class="['w-4 h-4 text-slate-600 transition-transform duration-200', expandedRecommendations[item.id] ? 'rotate-90' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <!-- Empty state -->
                                <div v-if="filteredRecommendations.length === 0" class="py-12 text-center text-slate-600 font-mono text-sm italic px-6">
                                    No recommendations match the selected filter.
                                </div>
                            </div>

                        </div>

                        <!-- Right Panel -->
                        <div class="lg:col-span-4 flex flex-col gap-4 animate-fade-in">

                            <!-- View Implementation Guide button -->
                            <button class="w-full py-3 bg-transparent hover:bg-white/5 border border-white/10 rounded-xl text-sm font-semibold text-slate-300 hover:text-white flex items-center justify-center gap-2 cursor-pointer transition-all font-sans">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                                </svg>
                                View Implementation Guide
                            </button>

                            <!-- Improvement Potential card -->
                            <!--
                                Gauge SVG geometry:
                                  viewBox 0 0 200 145, center=(100,110), radius=82
                                  Full semicircle: M18,110 A82,82 0 0 1 182,110
                                  Total arc length = π×82 ≈ 258
                                  60% fill = 155 units → stroke-dasharray="155 258"
                                  Dot at 60% of 180° → 108° from left start → 72° from +x
                                    cx=100+82×cos(72°)=100+25.3=125
                                    cy=110-82×sin(72°)=110-78.0=32
                                  Labels centered at arc interior bottom (≈ y=100,115)
                            -->
                            <div class="p-5 bg-[#0b0c0f] border border-white/5 rounded-2xl text-center shadow-lg shadow-black/40 shrink-0" style="overflow:hidden;">
                                <h5 class="text-xs font-black text-slate-500 uppercase tracking-widest font-mono mb-1">IMPROVEMENT POTENTIAL</h5>
                                <span class="text-3xl font-black text-[#CBB48A] font-mono leading-none">+28</span>
                                <p class="text-xs text-slate-500 font-sans mt-1">Potential Score Increase</p>
                                <svg viewBox="0 0 200 145" width="100%" height="145" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                                    <!-- Track (full semicircle) -->
                                    <path d="M 18 110 A 82 82 0 0 1 182 110"
                                        fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="11" stroke-linecap="round"/>
                                    <!-- Gold arc (~60% fill) -->
                                    <path d="M 18 110 A 82 82 0 0 1 182 110"
                                        fill="none" stroke="#CBB48A" stroke-width="11" stroke-linecap="round"
                                        stroke-dasharray="155 258"/>
                                    <!-- Gold endpoint dot at 60% (~1 o'clock, upper-right) -->
                                    <circle cx="125" cy="32" r="8" fill="#CBB48A"/>
                                    <!-- Centered labels inside arc bottom-interior -->
                                    <!-- "32" left of center -->
                                    <text x="72" y="102" fill="#cbd5e1" font-size="18" font-weight="900" font-family="monospace" text-anchor="middle">32</text>
                                    <text x="72" y="118" fill="#64748b" font-size="9" font-family="sans-serif" text-anchor="middle">Current</text>
                                    <!-- arrow center -->
                                    <text x="100" y="102" fill="#64748b" font-size="14" font-family="monospace" text-anchor="middle">→</text>
                                    <!-- "60" right of center -->
                                    <text x="128" y="102" fill="#f1f5f9" font-size="18" font-weight="900" font-family="monospace" text-anchor="middle">60</text>
                                    <text x="128" y="118" fill="#64748b" font-size="9" font-family="sans-serif" text-anchor="middle">Potential</text>
                                </svg>
                            </div>


                            <!-- Top Risk Areas -->
                            <div class="p-5 bg-[#0b0c0f] border border-white/5 rounded-2xl space-y-3 shadow-lg shadow-black/40">
                                <h5 class="text-xs font-black text-slate-500 uppercase tracking-widest font-mono">TOP RISK AREAS</h5>
                                <div class="space-y-2.5 text-sm font-sans">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                                            Access Control
                                        </span>
                                        <span class="text-rose-400 font-bold font-mono">3</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-orange-400 shrink-0"></span>
                                            Outdated Components
                                        </span>
                                        <span class="text-orange-400 font-bold font-mono">2</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-yellow-400 shrink-0"></span>
                                            Security Headers
                                        </span>
                                        <span class="text-yellow-400 font-bold font-mono">2</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-yellow-400 shrink-0"></span>
                                            Third-Party Exposure
                                        </span>
                                        <span class="text-yellow-400 font-bold font-mono">2</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-300 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                                            Authentication
                                        </span>
                                        <span class="text-emerald-400 font-bold font-mono">1</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Implementation Notes -->
                            <div class="p-5 bg-[#0b0c0f] border border-white/5 rounded-2xl shadow-lg shadow-black/40">
                                <h5 class="text-xs font-black text-slate-500 uppercase tracking-widest font-mono mb-3">IMPLEMENTATION NOTES</h5>
                                <div class="flex gap-3 items-start">
                                    <div class="w-8 h-8 rounded-lg bg-[#CBB48A]/10 border border-[#CBB48A]/20 flex items-center justify-center text-[#CBB48A] shrink-0">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                                        </svg>
                                    </div>
                                    <p class="text-xs text-slate-400 font-sans leading-relaxed">
                                        Fixing the top 3 critical issues can improve your score by up to 20 points and significantly reduce your risk exposure.
                                    </p>
                                </div>
                            </div>

                        </div>

                    </div>

                    <!-- TAB: HISTORY -->
                    <div v-if="activeTab === 'history'" class="space-y-6 animate-fade-in">
                        <div class="p-6 bg-[#070709] rounded-2xl border border-white/5 shadow-lg shadow-black/40">
                            <h4 class="text-lg font-bold text-white uppercase tracking-widest mb-4 font-mono">Historical Scans Log</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left font-mono text-base">
                                    <thead>
                                        <tr class="border-b border-white/5 text-slate-500">
                                            <th class="pb-3 uppercase">Date</th>
                                            <th class="pb-3 uppercase">Audit Type</th>
                                            <th class="pb-3 uppercase">Status</th>
                                            <th class="pb-3 uppercase text-right">Risk Score</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        <tr v-for="h in props.history" :key="h.id" class="text-slate-300">
                                            <td class="py-3">{{ new Date(h.created_at).toLocaleString() }}</td>
                                            <td class="py-3 uppercase">Website</td>
                                            <td class="py-3 font-bold text-emerald-400">{{ h.status }}</td>
                                            <td class="py-3 text-right text-rose-400 font-bold">{{ h.score }}</td>
                                        </tr>
                                        <tr v-if="!props.history.length">
                                            <td colspan="4" class="py-6 text-center text-slate-600 italic">No historical runs recorded for this website.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                    </div>
                </div>
            </div>



        </div>

        <!-- DIALOG SUBMODALS -->

        <!-- 1. AI Findings Detail Modal -->
        <div v-if="showAIDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="showAIDetailsModal = false"></div>
            <div class="relative z-10 w-full max-w-2xl bg-[#0e0f12] border border-white/10 rounded-2xl shadow-2xl overflow-hidden font-sans">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white uppercase tracking-wider">AI Findings Detail</h3>
                    <button @click="showAIDetailsModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <div class="p-6 max-h-[60vh] overflow-y-auto custom-scrollbar space-y-3">
                    <div v-for="(finding, idx) in keyFindings" :key="idx" class="p-4 rounded-xl border border-white/5 bg-white/[0.01]">
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-base font-bold text-white">{{ finding.title }}</span>
                            <span class="text-xs font-mono bg-white/5 border border-white/15 px-1.5 py-0.2 rounded font-bold uppercase text-slate-400">{{ finding.severity }}</span>
                        </div>
                        <p class="text-base text-slate-400 font-light leading-relaxed">{{ finding.desc }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Detailed Vector Analysis Dialog -->
        <div v-if="showVectorDetailsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 font-sans">
            <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="showVectorDetailsModal = false"></div>
            <div class="relative z-10 w-full max-w-4xl bg-[#0e0f12] border border-white/10 rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white uppercase tracking-wider">Detailed Security Vector Analysis</h3>
                    <button @click="showVectorDetailsModal = false" class="text-slate-400 hover:text-white">✕</button>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[70vh] overflow-y-auto custom-scrollbar bg-black/20">
                    <div v-for="(value, key) in radarData" :key="key" class="p-4 rounded-xl border border-white/5 bg-white/[0.01]">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="text-base font-bold text-slate-300 uppercase font-mono tracking-wider">{{ formatLabel(String(key)) }}</h4>
                            <span class="text-xl font-mono font-bold text-[#CBB48A]">{{ Number(value).toFixed(0) }}</span>
                        </div>
                        <div class="w-full bg-slate-900 h-1.5 rounded-full overflow-hidden mb-3">
                            <div class="h-full bg-[#CBB48A] rounded-full" :style="{ width: value + '%' }"></div>
                        </div>
                        <p class="text-base text-slate-400 font-light">Comprehensive verification of metrics against standard network topology rules.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. LUME AI Support analytic chat -->
        <LumeAISupport 
            v-if="asset"
            :show="showAIModal" 
            mode="analytic"
            :asset="asset"
            @close="showAIModal = false" 
        />

        <!-- 4. Topology Details Modal -->
        <TopologyDetailsModal 
            :show="showTopologyDetailsModal"
            :nodes="topologyData?.nodes || []"
            @close="showTopologyDetailsModal = false"
        />

        <!-- 5. QA Testing and Penetration specification -->
        <PenetrationAndAQTesting
            v-if="asset"
            :show="showDeepScanConfirmation"
            :asset="asset"
            @close="showDeepScanConfirmation = false"
            @confirm="handleDeepScanConfirm"
            @open-credit-modal="showPurchaseModal = true"
            @completion="showDeepScanConfirmation = false"
        />

        <!-- 6. Project Analyst AI Assistant Modal -->
        <ProjectAnalystModal
            v-if="asset"
            :show="showProjectAnalystModal"
            :asset="asset"
            @close="showProjectAnalystModal = false"
        />

        <!-- 7. Credit Purchase Modal -->
        <CreditPurchaseModal
            :show="showPurchaseModal"
            @close="showPurchaseModal = false"
        />

        <!-- Evidence Details Modal -->
        <div v-if="showEvidenceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/85 backdrop-blur-sm" @click="showEvidenceModal = null"></div>
            <div class="relative z-10 w-full max-w-3xl bg-[#0e0f12] border border-white/10 rounded-2xl shadow-2xl overflow-hidden font-sans flex flex-col max-h-[85vh]">
                <div class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-white uppercase tracking-wider font-mono">
                        All {{ showEvidenceModal }} Evidence
                    </h3>
                    <button @click="showEvidenceModal = null" class="text-slate-400 hover:text-white transition-colors cursor-pointer text-lg">✕</button>
                </div>
                
                <!-- Search bar in Modal -->
                <div class="p-6 pb-4 border-b border-white/5 bg-[#0e0f12]">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input 
                            type="text" 
                            placeholder="Filter modal results..." 
                            v-model="evidenceModalSearchQuery"
                            class="block w-full pl-9 pr-3 py-2 bg-[#050507] border border-white/5 rounded-lg text-sm text-gray-300 placeholder-slate-500 focus:outline-none focus:border-[#CBB48A]/40 focus:ring-0 transition-all font-sans"
                        />
                    </div>
                </div>

                <div class="p-6 overflow-y-auto custom-scrollbar space-y-3 bg-black/20 flex-1">
                    <div v-for="item in filteredModalEvidence" :key="item.id" class="p-4 rounded-xl border border-white/5 bg-white/[0.01] flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span :class="[item.status === 'error' ? 'bg-red-500' : item.status === 'warning' ? 'bg-amber-500' : 'bg-emerald-500', 'w-2 h-2 rounded-full shrink-0']"></span>
                            <div>
                                <div class="text-base font-bold text-white font-mono">{{ item.name }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">{{ item.value }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4">
                            <span :class="[
                                item.category === 'http' ? 'text-blue-400 bg-blue-950/40 border border-blue-500/20' :
                                item.category === 'network' ? 'text-purple-400 bg-purple-950/40 border border-purple-500/20' :
                                item.category === 'code' ? 'text-emerald-400 bg-emerald-950/40 border border-emerald-500/20' :
                                item.category === 'config' ? 'text-yellow-400 bg-yellow-950/40 border border-yellow-500/20' :
                                item.category === 'file' ? 'text-orange-400 bg-orange-950/40 border border-orange-500/20' :
                                'text-slate-400 bg-slate-900/40 border border-slate-700/20',
                                'px-2 py-0.5 rounded text-[10px] uppercase font-mono'
                            ]">{{ item.type }}</span>
                            <span class="text-xs text-slate-550 font-mono">{{ item.time }}</span>
                        </div>
                    </div>
                    
                    <div v-if="filteredModalEvidence.length === 0" class="py-12 text-center text-slate-500 font-sans">
                        No results found.
                    </div>
                </div>
            </div>
        </div>

        <!-- 8. QA Results Modal -->
        <QAPenetrationResultsModal
            v-if="asset"
            :show="showQAResultsModal"
            :asset="asset"
            @close="showQAResultsModal = false"
            @open-ai-chat="showPentestAiModal = true"
            @re-scan="showDeepScanConfirmation = true"
        />

        <!-- Universal progress monitor -->
        <UniversalScanning
            v-if="showSecurityScanningModal"
            :show="showSecurityScanningModal"
            type="security"
            :target-name="asset.file_name"
            :progress="auditProgress?.progress || 0"
            :step="auditProgress?.step || 'Initializing scan...'"
            :details="auditProgress?.details"
            @view-results="async () => { await refreshSelectedAsset(); showSecurityScanningModal = false; showQAResultsModal = true; }"
            @close="showSecurityScanningModal = false"
        />

    </AuthenticatedLayout>

        <!-- Full Stack Modal -->
        <div v-if="showFullStackModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="showFullStackModal = false"></div>
            <div class="relative bg-[#070709] border border-white/10 rounded-2xl w-full max-w-4xl max-h-[80vh] flex flex-col shadow-2xl overflow-hidden animate-fade-in">
                <!-- Header -->
                <div class="p-5 border-b border-white/5 flex items-center justify-between bg-white/[0.01]">
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-black text-white uppercase tracking-wider font-mono">ALL DETECTED TECHNOLOGIES</h3>
                        <span class="px-2.5 py-1 rounded text-[10px] font-bold bg-[#CBB48A]/10 text-[#CBB48A] border border-[#CBB48A]/20 font-mono">24 ASSETS</span>
                    </div>
                    <button @click="showFullStackModal = false" class="text-slate-400 hover:text-white transition-colors cursor-pointer">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <!-- Body -->
                <div class="p-6 overflow-y-auto modal-scrollbar grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 bg-black/20">
                    <!-- Example Items -->
                    <div class="p-4 border border-white/5 rounded-xl bg-white/[0.02] flex items-center gap-4 hover:bg-white/[0.04] transition-colors group">
                        <div class="w-10 h-10 rounded bg-rose-500/10 flex items-center justify-center text-rose-500 border border-rose-500/20">Lav</div>
                        <div>
                            <div class="text-white font-bold text-sm font-mono">Laravel 11.x</div>
                            <div class="text-slate-500 text-xs">Backend Framework</div>
                        </div>
                    </div>
                    <div class="p-4 border border-white/5 rounded-xl bg-white/[0.02] flex items-center gap-4 hover:bg-white/[0.04] transition-colors group">
                        <div class="w-10 h-10 rounded bg-fuchsia-500/10 flex items-center justify-center text-fuchsia-500 border border-fuchsia-500/20">PHP</div>
                        <div>
                            <div class="text-white font-bold text-sm font-mono">PHP 8.2</div>
                            <div class="text-slate-500 text-xs">Language</div>
                        </div>
                    </div>
                    <div class="p-4 border border-white/5 rounded-xl bg-white/[0.02] flex items-center gap-4 hover:bg-white/[0.04] transition-colors group">
                        <div class="w-10 h-10 rounded bg-emerald-500/10 flex items-center justify-center text-emerald-500 border border-emerald-500/20">Vue</div>
                        <div>
                            <div class="text-white font-bold text-sm font-mono">Vue.js 3.x</div>
                            <div class="text-slate-500 text-xs">Frontend Framework</div>
                        </div>
                    </div>
                    <div class="p-4 border border-white/5 rounded-xl bg-white/[0.02] flex items-center gap-4 hover:bg-white/[0.04] transition-colors group">
                        <div class="w-10 h-10 rounded bg-cyan-500/10 flex items-center justify-center text-cyan-500 border border-cyan-500/20">CSS</div>
                        <div>
                            <div class="text-white font-bold text-sm font-mono">Tailwind CSS</div>
                            <div class="text-slate-500 text-xs">CSS Framework</div>
                        </div>
                    </div>
                    <div class="p-4 border border-white/5 rounded-xl bg-white/[0.02] flex items-center gap-4 hover:bg-white/[0.04] transition-colors group">
                        <div class="w-10 h-10 rounded bg-blue-500/10 flex items-center justify-center text-blue-500 border border-blue-500/20">SQL</div>
                        <div>
                            <div class="text-white font-bold text-sm font-mono">MySQL 8.0</div>
                            <div class="text-slate-500 text-xs">Database</div>
                        </div>
                    </div>
                    <div class="p-4 border border-white/5 rounded-xl bg-white/[0.02] flex items-center gap-4 hover:bg-white/[0.04] transition-colors group">
                        <div class="w-10 h-10 rounded bg-emerald-500/10 flex items-center justify-center text-emerald-500 border border-emerald-500/20">Ngx</div>
                        <div>
                            <div class="text-white font-bold text-sm font-mono">Nginx</div>
                            <div class="text-slate-500 text-xs">Web Server</div>
                        </div>
                    </div>
                    <div class="p-4 border border-white/5 rounded-xl bg-white/[0.02] flex items-center gap-4 hover:bg-white/[0.04] transition-colors group">
                        <div class="w-10 h-10 rounded bg-orange-500/10 flex items-center justify-center text-orange-500 border border-orange-500/20">CF</div>
                        <div>
                            <div class="text-white font-bold text-sm font-mono">Cloudflare</div>
                            <div class="text-slate-500 text-xs">CDN / Security</div>
                        </div>
                    </div>
                    <div class="p-4 border border-white/5 rounded-xl bg-white/[0.02] flex items-center gap-4 hover:bg-white/[0.04] transition-colors group">
                        <div class="w-10 h-10 rounded bg-amber-500/10 flex items-center justify-center text-amber-500 border border-amber-500/20">GA</div>
                        <div>
                            <div class="text-white font-bold text-sm font-mono">Google Analytics</div>
                            <div class="text-slate-500 text-xs">Analytics</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </template>


<style scoped>
.text-shadow {
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
}
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    border: 2px solid transparent;
    background-clip: content-box;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.15);
    background-clip: content-box;
}
.modal-scrollbar::-webkit-scrollbar {
    width: 5px;
    height: 5px;
}
.modal-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.modal-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(203, 180, 138, 0.25);
    border-radius: 10px;
}
.modal-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: rgba(203, 180, 138, 0.6);
}

@keyframes radar-sweep {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.radar-sweep-effect {
    transform-origin: 190px 140px;
    animation: radar-sweep 4s linear infinite;
}

.map-container-3d {
    perspective: 400px;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.map-svg-3d {
    transform: rotateX(24deg) rotateY(-4deg) rotateZ(1deg);
    transform-style: preserve-3d;
    filter: drop-shadow(0 8px 16px rgba(0, 0, 0, 0.6)) drop-shadow(0 2px 5px rgba(203, 180, 138, 0.05));
    transition: transform 0.5s ease;
}

.map-svg-3d:hover {
    transform: rotateX(18deg) rotateY(-2deg) rotateZ(0.5deg);
}

/* Route dashed line march animation */
@keyframes dash-march {
    to { stroke-dashoffset: -44; }
}
.route-dash-animated {
    stroke-dashoffset: 0;
    animation: dash-march 1.4s linear infinite;
}

/* Geo-location target pulsing rings */
@keyframes geo-pulse-out {
    0%   { r: 11; opacity: 0.15; }
    60%  { r: 22; opacity: 0.04; }
    100% { r: 28; opacity: 0; }
}
@keyframes geo-pulse-in {
    0%   { r: 6;  opacity: 0.2; }
    50%  { r: 13; opacity: 0.08; }
    100% { r: 18; opacity: 0; }
}
.geo-pulse-outer {
    animation: geo-pulse-out 2.2s ease-out infinite;
}
.geo-pulse-inner {
    animation: geo-pulse-in 2.2s ease-out infinite 0.5s;
}

/* Map modal transition */
.map-modal-enter-active,
.map-modal-leave-active {
    transition: opacity 0.28s ease, transform 0.28s ease;
}
.map-modal-enter-from,
.map-modal-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
/* Technology map pulsing ring */
@keyframes pulse-ring {
    0% { transform: scale(0.8); opacity: 0.5; }
    100% { transform: scale(1.5); opacity: 0; }
}
.pulse-circle {
    animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
    transform-origin: center;
}
</style>
