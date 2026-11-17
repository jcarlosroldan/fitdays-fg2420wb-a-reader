<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width">
<title>Weights</title>
<style>
@font-face{font-family:Hershey;src:url(font.otf)}
:root{--blue:#4fb6f2;--blue-mid:#3487c4;--amber:#ffb340;--white:#eef6f8;--red:#ff5d40}
html,body{margin:0;height:100%;background:#000}
body{color:var(--blue);font:14px Hershey,system-ui,sans-serif;text-transform:uppercase;letter-spacing:.08em;text-shadow:0 0 1.5px rgba(79,182,242,.9),0 0 6px rgba(79,182,242,.4);display:grid;grid-template-columns:minmax(220px,1fr) auto auto minmax(220px,1fr)}
.figure{position:relative;height:100vh;& canvas{position:absolute;inset:0;width:100%;height:100%}}
.readings{padding:32px 24px 43px;display:flex;flex-direction:column;gap:28px;color:var(--slot);--glow:0 0 2px var(--slot),0 0 8px var(--slot)}
h1{margin:0;font-weight:400;font-size:34px;letter-spacing:.25em;text-shadow:var(--glow)}
time{display:block;margin-top:6px;color:var(--blue-mid);font-size:12px}
.tiles{display:grid;grid-template-columns:repeat(4,auto);justify-content:space-between;align-items:start;gap:14px 16px;& button:nth-child(-n+4){align-self:end;margin-bottom:14px;& b{font-size:26px}}& button:first-child b{font-size:30px}& button:nth-child(n+5) small{contain:inline-size}}
button{all:unset;cursor:pointer;display:block;&[aria-pressed=true] small{color:var(--white);text-shadow:0 0 4px var(--white)}& b{display:block;font-weight:400;font-size:20px;text-shadow:var(--glow);letter-spacing:.02em;white-space:nowrap}& b.words{white-space:normal;font-size:15px}& small{display:block;margin-top:4px;font-size:10px;line-height:1.3;color:var(--blue-mid)}& i{font-style:normal;font-size:11px;color:var(--amber);text-shadow:0 0 4px var(--amber);margin-left:6px}}
.percent{display:inline-block;width:.5em;height:.72em;fill:none;stroke:currentColor;stroke-width:1;filter:drop-shadow(0 0 1.5px currentColor);& *{vector-effect:non-scaling-stroke}}
.unit{font-size:.65em}
.chart{position:relative;flex:1;min-height:160px;& svg{position:absolute;top:0;left:0;width:100%;overflow:visible;font:10px Hershey,system-ui,sans-serif;letter-spacing:.08em}& .axis{stroke:var(--red);fill:none;filter:drop-shadow(0 0 2px var(--red))}& .axis-label{fill:var(--red);text-shadow:0 0 4px var(--red)}& .trend{stroke:var(--amber);fill:none;stroke-width:1.2;stroke-dasharray:4 3;filter:drop-shadow(0 0 2px var(--amber))}& .line{stroke:var(--white);fill:none;stroke-width:1.3;filter:drop-shadow(0 0 2px var(--white))}& .point{fill:var(--white)}& .point-label{fill:var(--white);text-shadow:0 0 4px var(--white)}}
.about{margin:0;height:4.8em;contain:inline-size;color:var(--white);font-size:12.5px;line-height:1.6;text-shadow:0 0 4px var(--white)}
@media (max-width:1400px){body{grid-template-columns:1fr 1fr}.readings{min-width:0}.figure{height:80vh}.figure:nth-of-type(2){order:4}.readings:nth-of-type(2){order:3}}
@media (max-width:950px){.tiles{grid-template-columns:repeat(4,1fr);& button:nth-child(-n+4){grid-column:span 2}}}
@media (max-width:760px){body{grid-template-columns:1fr}.figure:nth-of-type(2){order:3}.readings:nth-of-type(2){order:4}.tiles{grid-template-columns:repeat(2,1fr);& button:nth-child(-n+4){grid-column:auto}}}
</style>
</head>
<body>
<script src="three.js"></script>
<script>
const MODELS = <?= file_get_contents('models.json') ?>;
const PEOPLE = <?= file_get_contents('people.json') ?>;
const READINGS = [<?= str_replace("\n", ',', file_get_contents('readings.jsonl')) ?>];
const TILES = [
	['weight', 'Weight', 'kg', 2, 'Click on any index to see it in detail.'],
	['bfr', 'Body fat', '%', 1, 'The share of your weight that is fat. Men need at least 3% and women 12% to live. The scale guesses it from a weak current, so drinking can move it.'],
	['fatMass', 'Fat mass', 'kg', 1, 'How many kilos of fat you carry. Compare it with lean mass: if fat goes down and lean mass stays, you are losing the right weight.'],
	['leanMass', 'Lean mass', 'kg', 1, 'Everything in your body that is not fat. When you diet, try to keep this number steady so the weight you lose is fat.'],
	['weightTarget', 'Target weight', 'kg', 1, 'Your weight if you lost only your extra fat. Extra fat is any fat above 15% of your ideal weight, or 23% for women.'],
	['weightStandard', 'Ideal weight', 'kg', 1, 'The weight that would give you a BMI of 22, or 21 for women. It only looks at your height, so training does not change it.'],
	['age', 'Body age', 'years', 0, 'Your real age, moved a few years up or down depending on your body fat. It is a rough comparison, not a medical test.'],
	['bmr', 'Metabolism', 'kcal', 0, 'Calories your body would burn in a day lying in bed. Moving around adds between 20% and 90% on top of this.'],
	['muscle', 'Muscle', '%', 1, 'Water and protein as a share of your weight. Organs count as muscle here too, so this rises whenever your fat goes down.'],
	['bmi', 'BMI', '', 1, 'Your weight in kilos divided by your height in metres, squared. Between 18.5 and 25 is normal, but muscle raises it just like fat.'],
	['proteinMass', 'Protein mass', 'kg', 1, 'Protein is not measured. It is taken as 20% of your lean mass, so this always moves together with lean mass.'],
	['waterMass', 'Water mass', 'kg', 1, 'Taken as 73.3% of your lean mass. Real water changes by a kilo or two from day to day after salty food or exercise.'],
	['muscleMass', 'Muscle mass', 'kg', 1, 'Your lean mass without the bones. It includes your organs and the water in them, so it is bigger than the muscle you can train.'],
	['water', 'Water', '%', 1, 'The share of your weight that is water. Most men are between 50% and 65% and most women between 45% and 60%.'],
	['smi', 'Skeletal muscle', 'kg/m²', 1, 'Muscle in your arms and legs divided by your height in metres, squared. Under 7.0 for men or 5.7 for women can mean muscle loss.'],
	['sm', 'Skeletal muscle', '%', 1, 'The share of your weight made of the muscles you move with. On average it is about 38% for men and 31% for women.'],
	['armBalance', 'Arm balance', '', null, 'Whether both arms have about the same muscle. Your stronger arm is usually a bit bigger, and that is normal.'],
	['legBalance', 'Leg balance', '', null, 'Whether both legs have about the same muscle. Legs are usually very even, so a difference often comes from an old injury.'],
	['armAndLegBalance', 'Upper / lower balance', '', null, 'Whether your arms and legs have similar muscle for your size. Sports like running or cycling often make the legs win.'],
	['protein', 'Protein', '%', 1, 'Protein as a share of your weight. It goes down when you gain fat, even if you eat more protein, because protein is not measured.'],
];
const { clamp, lerp, smoothstep } = THREE.MathUtils;
const { body: BODY, torso: TORSO, composition: COMPOSITION } = MODELS;
const MARGIN = { left: 44, right: 12, top: 14, bottom: 22 };
const BLUE = '#4fb6f2';
const BLUE_MID = '#3487c4';
const AMBER = '#ffb340';
const FRONT = 0;
const OUTSIDE = { 1: 7, [-1]: 21 };
const BACK = 14;
const EXPONENT = 2.2;
const SPACING = 0.022;
const DETAIL = 3;
const SEGMENTS = ['trunk', 'leftArm', 'rightArm', 'leftLeg', 'rightLeg'];

function render() {
	const people = PEOPLE.map((person, index) => {
		const weighings = READINGS.filter(reading => reading.person.name === person.name && reading.person.confidence >= 0.5);
		const compositions = weighings.map(({ time, weight, impedances }) => impedances.every(Boolean) ? compose(weight, person.height, Math.floor((Date.parse(time) - Date.parse(person.birthdate)) / 31556952000), person.sex, impedances) : null);
		return {
			...person,
			color: ['#5be381', '#ff73cf'][index],
			history: weighings.map(({ time, weight }, i) => ({ time: Date.parse(time) / 1000, values: { weight, ...compositions[i]?.values } })),
			segments: compositions.findLast(Boolean)?.segments,
		};
	});
	setInterval(async () => (await (await fetch('readings.jsonl', { cache: 'no-store' })).text()).trim().split('\n').length !== READINGS.length && location.reload(), 60000);
	const tallest = Math.max(...people.map(person => person.height));
	const panels = people.map(person => ({ person, figure: element('div', 'figure', '<canvas></canvas>'), ...readings(person) }));
	const [first, second] = panels;
	document.body.append(first.figure, first.section, second.section, second.figure);
	panels.forEach(({ person, figure }, index) => {
		new Figure(figure.firstChild, person, tallest, index ? -0.4 : 0.4);
	});
	let selected = TILES[0][0];
	const select = key => {
		selected = key;
		const [, , , decimals, about] = TILES.find(([tile]) => tile === key);
		for (const { person, section, tiles } of panels) {
			Object.entries(tiles).forEach(([tile, button]) => button.setAttribute('aria-pressed', tile === key));
			drawChart(section.querySelector('.chart'), person.history, key, decimals);
			section.querySelector('.about').textContent = about;
		}
	};
	for (const { tiles } of panels) {
		Object.entries(tiles).forEach(([key, button]) => button.addEventListener('click', () => select(key)));
	}
	new ResizeObserver(() => select(selected)).observe(first.section.querySelector('.chart'));
}

function readings(person) {
	const latest = person.history.at(-1);
	const section = element('section', 'readings', `<header><h1>${person.name}</h1><time>${latest ? new Date(latest.time * 1000).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) : ''}</time></header><div class="tiles"></div><p class="about"></p><div class="chart"></div>`);
	section.style.setProperty('--slot', person.color);
	const tiles = {};
	TILES.forEach(([key, label, unit, decimals]) => {
		const last = series(person.history, key).at(-1);
		tiles[key] = element('button', '', `<b${decimals === null ? ' class="words"' : ''}>${last ? format(last.value, decimals) : '--'} <span class="unit">${unit === '%' ? '<svg class="percent" viewBox="0 0 10 14"><circle cx="2.5" cy="3" r="2"/><circle cx="7.5" cy="11" r="2"/><path d="M8.5 1.5 1.5 12.5"/></svg>' : unit}</span></b><small>${label}${trend(person.history, key, decimals)}</small>`);
		section.querySelector('.tiles').append(tiles[key]);
	});
	return { section, tiles };
}

function element(tag, className, html) {
	const node = document.createElement(tag);
	node.className = className;
	node.innerHTML = html;
	return node;
}

function trend(history, key, decimals) {
	const points = series(history, key);
	if (decimals === null || history.at(-1)?.values[key] === undefined || points.length < 2) {
		return '';
	}
	const smooth = smoothed(points);
	const latest = smooth.at(-1);
	const change = latest.value - (smooth.findLast(point => point.time <= latest.time - 28 * 86400) ?? smooth[0]).value;
	return `<i>${Math.abs(change) < 0.05 ? '=' : `${change > 0 ? '▲' : '▼'} ${Math.abs(change).toFixed(1)}`}</i>`;
}

function series(history, key) {
	return history.filter(entry => entry.values[key] !== undefined).map(entry => ({ time: entry.time, value: entry.values[key] }));
}

function smoothed(points) {
	let trend = points[0].value;
	return points.map((point, i) => ({ time: point.time, value: trend += (point.value - trend) * (1 - Math.exp(((points[i - 1]?.time ?? point.time) - point.time) / 864000)) }));
}

function format(value, decimals) {
	return decimals === null ? ['Balanced', 'Slightly unbalanced', 'Unbalanced'][value] ?? '--' : value.toFixed(decimals);
}

function drawChart(container, history, key, decimals) {
	const points = series(history, key);
	const width = container.clientWidth;
	const height = Math.min(container.clientHeight, width);
	if (!points.length || !width) {
		container.replaceChildren();
		return;
	}
	const times = points.map(point => point.time);
	const values = points.map(point => point.value);
	const [first, last] = [Math.min(...times), Math.max(...times)];
	const [low, high] = [Math.min(...values), Math.max(...values)];
	const pad = (high - low) * 0.1 || Math.max(Math.abs(high) * 0.05, 1);
	const x = time => MARGIN.left + (last === first ? 0.5 : (time - first) / (last - first)) * (width - MARGIN.left - MARGIN.right);
	const y = value => MARGIN.top + (1 - (value - low + pad) / (high - low + 2 * pad)) * (height - MARGIN.top - MARGIN.bottom);
	const bottom = height - MARGIN.bottom;
	const date = time => new Date(time * 1000).toLocaleDateString([], { day: 'numeric', month: 'short' });
	const label = (className, x, y, value, anchor) => `<text class="${className}" x="${x}" y="${y}" text-anchor="${anchor}">${format(value, decimals).split(' ').map((word, index, words) => `<tspan x="${x}" dy="${index ? 1.1 : -1.1 * (words.length - 1)}em">${word}</tspan>`).join('')}</text>`;
	const labelled = [...new Set([0, points.length - 1, values.indexOf(low), values.indexOf(high)])];
	const daily = smoothed(points).filter((point, i, all) => new Date(point.time * 1000).toDateString() !== new Date((all[i + 1]?.time ?? Infinity) * 1000).toDateString()).map(({ time, value }) => [x(time), y(value)]);
	const slopes = daily.slice(1).map(([x1, y1], i) => (y1 - daily[i][1]) / Math.max(x1 - daily[i][0], 1e-6));
	const tangents = daily.map((point, i) => {
		const [left, right] = [slopes[i - 1] ?? slopes[i], slopes[i] ?? slopes[i - 1]];
		return left * right > 0 ? 2 / (1 / left + 1 / right) : 0;
	});
	const curve = daily.slice(1).map(([x1, y1], i) => {
		const [x0, y0] = daily[i];
		const third = (x1 - x0) / 3;
		return `C${x0 + third},${y0 + tangents[i] * third} ${x1 - third},${y1 - tangents[i + 1] * third} ${x1},${y1}`;
	}).join(' ');
	container.innerHTML = `<svg viewBox="0 0 ${width} ${height}" height="${height}">
		<path class="axis" d="M${MARGIN.left} ${MARGIN.top}V${bottom}H${width - MARGIN.right}"/>
		${label('axis-label', MARGIN.left - 6, y(high) + 3, high, 'end')}
		${label('axis-label', MARGIN.left - 6, y(low) + 3, low, 'end')}
		<text class="axis-label" x="${MARGIN.left}" y="${bottom + 15}">${date(first)}</text>
		<text class="axis-label" x="${width - MARGIN.right}" y="${bottom + 15}" text-anchor="end">${date(last)}</text>
		<path class="trend" d="M${daily[0][0]},${daily[0][1]} ${curve}"/>
		<polyline class="line" points="${points.map(point => `${x(point.time)},${y(point.value)}`).join(' ')}"/>
		${points.map(point => `<circle class="point" cx="${x(point.time)}" cy="${y(point.value)}" r="1.8"/>`).join('')}
		${labelled.map(index => label('point-label', x(points[index].time), y(points[index].value) - 7, points[index].value, 'middle')).join('')}
	</svg>`;
}

class Figure {
	constructor(screen, person, tallest, turn) {
		this.screen = screen;
		this.context = screen.getContext('2d');
		this.small = document.createElement('canvas');
		this.person = person;
		this.tallest = tallest;
		this.renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true });
		this.renderer.outputColorSpace = THREE.LinearSRGBColorSpace;
		this.scene = new THREE.Scene();
		this.scene.fog = new THREE.Fog(0x000000, 1, 2);
		this.beams = new Beams();
		this.volume = new Volume();
		this.stage = new THREE.Group();
		this.stage.rotation.y = turn;
		this.stage.add(this.beams.group, this.volume.mesh);
		this.scene.add(this.stage);
		this.stage.updateMatrixWorld();
		this.camera = new THREE.PerspectiveCamera(24, 1, 5, 5000);
		this.camera.position.set(1, tallest / 2, 0);
		this.controls = new THREE.OrbitControls(this.camera, screen);
		Object.assign(this.controls, { enablePan: false, enableZoom: false, enableDamping: true, rotateSpeed: 0.7, minPolarAngle: Math.PI * 0.3, maxPolarAngle: Math.PI * 0.62 });
		this.controls.target.set(0, tallest / 2, 0);
		this.controls.addEventListener('start', () => this.dragging = true);
		this.controls.addEventListener('end', () => this.dragging = false);
		this.body = new Body(person, this.beams, this.volume);
		new ResizeObserver(() => this.resize()).observe(screen);
		this.last = -Infinity;
		this.renderer.setAnimationLoop(now => this.frame(now));
	}

	resize() {
		const { clientWidth: width, clientHeight: height } = this.screen;
		const ratio = Math.min(2, devicePixelRatio);
		this.screen.width = Math.round(width * ratio);
		this.screen.height = Math.round(height * ratio);
		this.renderer.setPixelRatio(ratio);
		this.renderer.setSize(width, height, false);
		this.beams.setResolution(width, height);
		this.camera.aspect = width / height;
		this.camera.updateProjectionMatrix();
		const halfView = Math.tan(Math.PI / 15);
		const target = this.controls.target;
		this.camera.position.sub(target).setLength(Math.max(0.54 * this.tallest / halfView, 0.3 * this.tallest / (halfView * this.camera.aspect))).add(target);
	}

	frame(now) {
		if (now - this.last < 1000 / 24) {
			return;
		}
		const delta = Math.min(0.05, (now - this.last) / 1000);
		this.last = now;
		const target = this.controls.target;
		if (!this.dragging) {
			const spherical = new THREE.Spherical().setFromVector3(this.camera.position.clone().sub(target));
			const ease = 1 - Math.exp(-delta * 1.2);
			spherical.theta += Math.atan2(Math.sin(Math.PI / 2 - spherical.theta), Math.cos(Math.PI / 2 - spherical.theta)) * ease;
			spherical.phi += (Math.PI / 2 - 0.14 - spherical.phi) * ease;
			this.camera.position.setFromSpherical(spherical).add(target);
		}
		this.controls.update();
		const distance = this.camera.position.distanceTo(target);
		this.scene.fog.near = distance - 20;
		this.scene.fog.far = distance + 40;
		this.beams.begin();
		this.volume.count = 0;
		this.body.draw(delta, this.stage.worldToLocal(this.camera.position.clone()));
		this.beams.end();
		this.volume.end();
		this.renderer.render(this.scene, this.camera);
		const { screen, context, small } = this;
		context.globalCompositeOperation = 'copy';
		context.drawImage(this.renderer.domElement, 0, 0, screen.width, screen.height);
		small.width = Math.round(screen.width / 4);
		small.height = Math.round(screen.height / 4);
		const smallContext = small.getContext('2d');
		smallContext.filter = `blur(${this.renderer.getPixelRatio() / 2}px)`;
		smallContext.drawImage(screen, 0, 0, small.width, small.height);
		context.globalCompositeOperation = 'screen';
		context.globalAlpha = 0.75;
		context.drawImage(small, 0, 0, screen.width, screen.height);
		context.globalAlpha = 1;
		context.globalCompositeOperation = 'source-over';
		this.drawLabels();
	}

	drawLabels() {
		const segments = this.person.segments;
		if (!segments) {
			return;
		}
		const { context, screen } = this;
		const project = point => {
			const { x, y } = this.stage.localToWorld(point.clone()).project(this.camera);
			return [(x + 1) / 2 * screen.clientWidth, (1 - y) / 2 * screen.clientHeight];
		};
		const [middle] = project(new THREE.Vector3(0, this.person.height / 2, 0));
		const ratio = this.renderer.getPixelRatio();
		context.save();
		context.setTransform(ratio, 0, 0, ratio, 0, 0);
		context.font = '400 10px Hershey';
		context.textBaseline = 'middle';
		for (const [segment, title] of Object.entries({ leftArm: 'Left arm', rightArm: 'Right arm', trunk: 'Trunk', leftLeg: 'Left leg', rightLeg: 'Right leg' })) {
			const values = segments[segment];
			const [x, y] = project(this.body.anchors[segment]);
			const lines = [
				[title.toUpperCase(), BLUE_MID],
				[`MUSCLE ${values.muscleMass.toFixed(1)}KG`, this.person.color],
				[versusAverage(values.muscle), this.person.color],
				[`FAT ${values.fatMass.toFixed(1)}KG`, AMBER],
				[versusAverage(values.fat), AMBER],
			];
			const textWidth = Math.max(...lines.map(([text]) => context.measureText(text).width));
			const left = segment === 'trunk' ? x - textWidth / 2 : x < middle ? Math.max(4, x - textWidth) : Math.min(screen.clientWidth - textWidth - 4, x);
			lines.forEach(([text, color], index) => {
				context.fillStyle = context.shadowColor = color;
				for (const blur of [14, 4]) {
					context.shadowBlur = blur;
					context.fillText(text, left, y + (index - 2) * 13);
				}
			});
		}
		context.restore();
	}
}

function versusAverage(percent) {
	const difference = Math.round(percent - 100);
	return difference === 0 ? 'AT AVG' : `${Math.abs(difference)}% ${difference > 0 ? 'OVER' : 'UNDER'} AVG`;
}

class Body {
	constructor(person, beams, volume) {
		this.beams = beams;
		this.volume = volume;
		this.height = person.height;
		this.color = person.color;
		this.segments = person.segments;
		const weight = person.history.at(-1)?.values.weight;
		this.girth = weight ? clamp(Math.sqrt(weight / (person.height / 100) ** 2 / 22), 0.85, 1.3) : 1;
		const json = MODELS.heads[person.sex];
		const male = person.sex === 'male';
		const jaw = male ? 0 : 0.12;
		const k = json.scale;
		const { crown, chinBottom, headPivot, mouth, eyes } = json.anchors;
		const bottom = json.rings[0];
		const front = Math.max(...bottom.map(([x]) => x));
		const back = Math.min(...bottom.map(([x]) => x));
		const base = new THREE.Vector3((front + back) / 2, bottom[0][1], 0);
		const scale = BODY.head * person.height / (crown[1] - chinBottom[1]);
		const rest = points => points.map(([x, y, z]) => ({ point: new THREE.Vector3(x, y, z * (1 - jaw * (1 - smoothstep(y, mouth[1], eyes[1])))), weight: smoothstep(y, base.y, headPivot[1]) }));
		const surface = headSurface(json.rings, json.profile, crown);
		const rings = [...json.rings.map(ring => ring.reduce((sum, [, y]) => sum + y, 0) / ring.length), crown[1]].map(y => rest(angles(0, 2 * Math.PI, 64).slice(0, -1).map(angle => surface(angle, y))));
		const widest = out => rest([...json.rings.map(ring => ring.reduce((best, point) => out * point[2] > out * best[2] ? point : best)), crown]);
		this.head = {
			rings,
			sides: { 1: widest(1), [-1]: widest(-1) },
			scale,
			base,
			pivot: new THREE.Vector3(...headPivot),
			eyeRig: json.eyeRig,
			brow: male ? { arch: 0.04, width: 1.6 } : { arch: 0.12, width: 1.3 },
			profile: rest(json.profile.filter(([, y]) => y > base.y)),
			features: [
				...json.features,
				{ alpha: 0.8, points: angles(-2.3 * k, 2.3 * k, 12).map(across => [mouth[0] - 1.3 * k * (across / (3 * k)) ** 2, mouth[1] + (male ? -1 : 1) * 0.25 * k * (across / (2.3 * k)) ** 2, across]) },
				...(male ? [...shortHair(surface, k), ...beard(surface, k)] : tiedHair(surface, k)),
			].map(line => ({ color: BLUE_MID, width: 1.05, ...line, rest: rest(line.points) })),
		};
		this.rows = [...TORSO[person.sex].map(([y, width, depth, x]) => [y * this.height, width * this.height * this.girth, depth * this.height * this.girth, x * this.height]), [person.height - (crown[1] - base.y) * scale, Math.max(...bottom.map(([, , z]) => z)) * scale * (1 - jaw), (front - back) / 2 * scale, TORSO[person.sex].at(-1)[3] * this.height]];
		this.motion = new Motion();
		this.anchors = {};
	}

	draw(delta, camera) {
		const pose = this.motion.update(delta);
		const h = this.height;
		const side = smoothstep(Math.abs(camera.clone().sub(new THREE.Vector3(0, h / 2, 0)).normalize().z), 0.25, 0.8);
		const frontal = 0.22 + 0.78 * (1 - side);
		const sagittal = 0.22 + 0.78 * side;
		this.drop = 0;
		const loaded = pose.shift >= 0 ? 1 : -1;
		const [hip, ankle] = [this.hipJoint(loaded, pose), this.ankle(loaded, pose)];
		this.drop = hip.y - ankle.y - Math.sqrt((0.995 * (BODY.thigh + BODY.shin) * h) ** 2 - (hip.x - ankle.x) ** 2 - (hip.z - ankle.z) ** 2);
		const [bottom, top] = [this.rows[0][0], this.rows.at(-1)[0]];
		const torso = angles(bottom, top, DETAIL * Math.round((top - bottom) / (SPACING * h))).map(y => {
			const [width, depth] = this.section(y, pose);
			const { center, roll } = this.torsoFrame(y, pose);
			const across = new THREE.Vector3(0, -Math.sin(roll), Math.cos(roll));
			return { center, roll, across, width, depth, points: outline(center, new THREE.Vector3(1, 0, 0), across, depth, width) };
		});
		this.drawRings(torso.map(ring => ring.points), this.segments?.trunk);
		this.anchors.trunk = this.torsoPoint(0.6 * h, this.section(0.62 * h, pose)[1], 0, pose);
		const legs = { [-1]: this.drawLeg(pose, -1, torso[0]), 1: this.drawLeg(pose, 1, torso[0]) };
		const arms = { [-1]: this.drawArm(pose, -1), 1: this.drawArm(pose, 1) };
		const { scale, base, pivot, rings, sides, profile, features } = this.head;
		const neck = new THREE.Matrix4().makeTranslation(torso.at(-1).center).multiply(new THREE.Matrix4().makeRotationX(torso.at(-1).roll)).scale(new THREE.Vector3(scale, scale, scale)).multiply(new THREE.Matrix4().makeTranslation(base.clone().negate()));
		const { nod, turn, tilt } = pose.head;
		const skull = neck.clone().multiply(new THREE.Matrix4().makeTranslation(pivot)).multiply(new THREE.Matrix4().makeRotationFromEuler(new THREE.Euler(tilt, turn, nod, 'YZX'))).multiply(new THREE.Matrix4().makeTranslation(pivot.clone().negate()));
		const place = ({ point, weight }) => point.clone().applyMatrix4(neck).lerp(point.clone().applyMatrix4(skull), weight);
		const head = rings.map(ring => ring.map(place));
		this.drawRings(head);
		for (const line of features) {
			this.beams.line(line.rest.map(place), line.color, line.width, line.alpha, line.closed);
		}
		this.drawEyes(pose.eyes, camera.clone().applyMatrix4(skull.clone().invert()), skull);
		const contour = out => sides[out].map(place);
		const shoulder = out => new THREE.CubicBezierCurve3(contour(out)[0], contour(out)[0].clone().add(new THREE.Vector3(0, -0.006 * h, out * 0.02 * h)), arms[out][0][0].clone().add(new THREE.Vector3(0, 0, -out * 0.025 * h)), arms[out][0][0]).getPoints(12);
		this.beams.line([...shoulder(-1).reverse(), ...contour(-1).slice(1), ...contour(1).slice(1).reverse(), ...shoulder(1)], BLUE, 1.6, frontal);
		this.beams.line([...torso.map(ring => ring.points[FRONT]), ...profile.map(place), ...torso.toReversed().map(ring => ring.points[BACK]), torso[0].center], BLUE, 1.6, sagittal, true);
		for (const out of [-1, 1]) {
			const tube = arms[out].map(ring => {
				const center = ring.reduce((sum, point) => sum.add(point), new THREE.Vector3()).divideScalar(ring.length);
				return { center, radius: ring.reduce((sum, point) => sum + point.distanceTo(center), 0) / ring.length };
			});
			const depth = point => Math.min(...tube.map(({ center, radius }) => point.distanceTo(center) - radius));
			const side = torso.map(ring => ring.points[OUTSIDE[out]]).filter(point => point.y < arms[out][0][0].y);
			const entry = side.findIndex(point => depth(point) < 0);
			this.beams.line(entry < 1 ? side : [...side.slice(0, entry), side[entry - 1].clone().lerp(side[entry], depth(side[entry - 1]) / (depth(side[entry - 1]) - depth(side[entry])))], BLUE, 1.6, frontal);
			for (const limb of [arms[out], legs[out]]) {
				this.beams.line([...meridian(limb, OUTSIDE[out]), ...meridian(limb, OUTSIDE[-out]).reverse()], BLUE, 1.6, frontal);
				this.beams.line([...meridian(limb, FRONT), ...meridian(limb, BACK).reverse()], BLUE, 1.6, sagittal);
			}
		}
	}

	section(y, pose) {
		const above = this.rows.findIndex(row => row[0] >= y);
		const [low, high] = above <= 0 ? [this.rows[0], this.rows[0]] : [this.rows[above - 1], this.rows[above]];
		const u = high[0] === low[0] ? 0 : (y - low[0]) / (high[0] - low[0]);
		const chest = bump(y, 0.72 * this.height, 0.07 * this.height) * pose.breath;
		const belly = bump(y, 0.6 * this.height, 0.05 * this.height) * pose.breath;
		return [lerp(low[1], high[1], u) * (1 + 0.015 * chest), lerp(low[2], high[2], u) * (1 + 0.04 * chest + 0.025 * belly), lerp(low[3], high[3], u)];
	}

	torsoFrame(y, pose) {
		const t = clamp((y - this.rows[0][0]) / (this.rows.at(-1)[0] - this.rows[0][0]), 0, 1);
		return {
			center: new THREE.Vector3(this.section(y, pose)[2], y - this.drop + pose.breath * 0.004 * this.height * t, pose.sway * this.height * (1 - 0.75 * t)),
			roll: lerp(pose.pelvisRoll, pose.chestRoll, smoothstep(t, 0.15, 0.85)),
		};
	}

	torsoPoint(y, forward, across, pose) {
		const { center, roll } = this.torsoFrame(y, pose);
		return center.add(new THREE.Vector3(forward, -across * Math.sin(roll), across * Math.cos(roll)));
	}

	hipJoint(out, pose) {
		return this.torsoPoint(BODY.hip * this.height, 0, out * BODY.hipWidth * this.height, pose);
	}

	ankle(out, pose) {
		const free = Math.max(0, -out * pose.shift);
		return new THREE.Vector3(0.03 * this.height * free, BODY.ankle * this.height, out * (BODY.stance - 0.012 * free) * this.height);
	}

	drawLeg(pose, out, bottom) {
		const h = this.height;
		const g = this.girth;
		const [knee, ankle] = reach(this.hipJoint(out, pose), this.ankle(out, pose), BODY.thigh * h, BODY.shin * h, new THREE.Vector3(1, 0, out * 0.1));
		const half = bottom.width / 2;
		this.anchors[out > 0 ? 'leftLeg' : 'rightLeg'] = knee.clone().lerp(ankle, 0.45).add(new THREE.Vector3(0, 0, out * 0.05 * h));
		return this.tube({
			path: [
				bottom.center.clone().addScaledVector(bottom.across, out * half),
				knee, knee.clone().lerp(ankle, 0.3), ankle,
				new THREE.Vector3(ankle.x + 0.012 * h, 0.022 * h, ankle.z),
				new THREE.Vector3(ankle.x + 0.075 * h, 0.012 * h, ankle.z + out * 0.01 * h),
				new THREE.Vector3(ankle.x + 0.11 * h, 0.009 * h, ankle.z + out * 0.016 * h),
			],
			radii: [[half, bottom.depth], [0.03 * h * g, 0.032 * h * g], [0.034 * h * g, 0.036 * h * g], [0.017 * h, 0.02 * h], [0.022 * h, 0.024 * h], [0.03 * h, 0.012 * h], [0.026 * h, 0.009 * h]],
			count: DETAIL * Math.round(0.6 / SPACING),
			caps: [0, 0.025 * h],
			across: bottom.across,
		}, this.segments?.[out > 0 ? 'leftLeg' : 'rightLeg']);
	}

	drawArm(pose, out) {
		const h = this.height;
		const g = this.girth;
		const shoulder = this.torsoPoint(BODY.shoulder * h, -0.005 * h, out * BODY.shoulderWidth * h * g, pose).add(new THREE.Vector3(0, pose.breath * 0.003 * h, 0));
		const [fromWrist, fromPole, fromHand] = this.armGoal(pose.arms.from, shoulder, out, pose);
		const [toWrist, toPole, toHand] = this.armGoal(pose.arms.to, shoulder, out, pose);
		const blend = smoothstep(pose.arms.blend, 0, 1);
		const [elbow, wrist] = reach(shoulder, fromWrist.lerp(toWrist, blend), BODY.upperArm * h, BODY.forearm * h, fromPole.lerp(toPole, blend));
		const hand = wrist.clone().sub(elbow).normalize().lerp(fromHand.lerp(toHand, blend), 0.7).normalize();
		const cap = BODY.cap * h * g;
		this.anchors[out > 0 ? 'leftArm' : 'rightArm'] = shoulder.clone().add(new THREE.Vector3(0, 0.08 * h, out * 0.01 * h));
		return this.tube({
			path: [shoulder.clone().addScaledVector(elbow.clone().sub(shoulder).normalize(), -cap), shoulder, elbow, elbow.clone().lerp(wrist, 0.25), wrist, wrist.clone().addScaledVector(hand, 0.45 * BODY.hand * h), wrist.clone().addScaledVector(hand, BODY.hand * h)],
			radii: [[0.028 * g, 0.03 * g], [0.028 * g, 0.03 * g], [0.019 * g, 0.02 * g], [0.022 * g, 0.021 * g], [0.014, 0.02], [0.013, 0.03], [0.012, 0.028]].map(([width, depth]) => [width * h, depth * h]),
			count: DETAIL * Math.round(0.46 / SPACING),
			caps: [cap, 0.012 * h],
		}, this.segments?.[out > 0 ? 'leftArm' : 'rightArm']);
	}

	armGoal(posture, shoulder, out, pose) {
		const h = this.height;
		if (posture === 'hips') {
			return [this.torsoPoint(0.58 * h, 0.01 * h, out * (this.section(0.58 * h, pose)[0] + 0.035 * h), pose), new THREE.Vector3(-0.5, 0, out), new THREE.Vector3(0.4, -1, -out * 0.25)];
		}
		if (posture === 'behind') {
			return [this.torsoPoint(0.53 * h, -(this.section(0.53 * h, pose)[1] + 0.03 * h), out * 0.03 * h, pose), new THREE.Vector3(-0.3, 0, out), new THREE.Vector3(0, -1, -out * 0.6)];
		}
		return [shoulder.clone().add(new THREE.Vector3(0.025 * h, -0.318 * h, out * 0.035 * h)), new THREE.Vector3(-1, 0, out * 0.2), new THREE.Vector3(0.1, -1, 0)];
	}

	tube({ path, radii, count, caps, across }, values) {
		const lengths = path.map((point, i) => i && point.distanceTo(path[i - 1]));
		lengths.forEach((length, i) => lengths[i] = i && lengths[i - 1] + length);
		const total = lengths.at(-1);
		const sample = distance => {
			const clamped = clamp(distance, 0, total);
			const piece = clamp(lengths.findIndex(length => length >= clamped) - 1, 0, path.length - 2);
			const u = (clamped - lengths[piece]) / (lengths[piece + 1] - lengths[piece]);
			return { point: path[piece].clone().lerp(path[piece + 1], u), radius: [0, 1].map(k => lerp(radii[piece][k], radii[piece + 1][k], u)) };
		};
		const forward = new THREE.Vector3(1, 0, 0);
		const rings = angles(0, total, count).map((distance, step) => {
			const { point, radius: [width, depth] } = sample(distance);
			const tangent = step === 0 && across ? new THREE.Vector3().crossVectors(forward, across) : sample(distance + SPACING * this.height).point.sub(sample(distance - SPACING * this.height).point).normalize();
			forward.addScaledVector(tangent, -forward.dot(tangent)).normalize();
			const tip = rounding(distance, caps[0]) * rounding(total - distance, caps[1]);
			return outline(point, forward, new THREE.Vector3().crossVectors(tangent, forward), depth * tip, width * tip);
		});
		this.drawRings(rings, values);
		return rings;
	}

	drawRings(rings, values) {
		const muscle = values && Math.sqrt(values.muscleMass / (values.muscleMass + values.fatMass));
		const inner = muscle && rings.map(ring => {
			const center = ring.reduce((sum, point) => sum.add(point), new THREE.Vector3()).divideScalar(ring.length);
			return ring.map(point => center.clone().lerp(point, muscle));
		});
		this.volume.tube(rings);
		rings.forEach((ring, index) => {
			if (index % DETAIL === 0) {
				this.beams.line(ring, '#1f5f8c', 0.85, 0.42, true);
			}
		});
		if (inner) {
			this.volume.tube(inner);
			inner.forEach((ring, index) => {
				if (index % (2 * DETAIL) === 0) {
					this.beams.line(ring, this.color, 1.05, 0.35, true);
				}
			});
		}
	}

	drawEyes(eyes, camera, skull) {
		const { eyeRig: { radius, centers }, brow } = this.head;
		const gaze = new THREE.Vector3(Math.cos(eyes.pitch) * Math.cos(eyes.yaw), Math.sin(eyes.pitch), Math.cos(eyes.pitch) * Math.sin(eyes.yaw));
		const seen = 1 - smoothstep(eyes.lid, 0.4, 0.9);
		const line = (points, color, width, alpha) => this.beams.line(points.map(point => point.applyMatrix4(skull)), color, width, alpha);
		const circle = (center, normal, size, count) => {
			const u = new THREE.Vector3(...(Math.abs(normal.y) > 0.9 ? [1, 0, 0] : [0, 1, 0])).cross(normal).normalize();
			const v = normal.clone().cross(u);
			return angles(0, 2 * Math.PI, count).map(angle => center.clone().addScaledVector(u, Math.cos(angle) * size).addScaledVector(v, Math.sin(angle) * size));
		};
		for (const position of centers) {
			const center = new THREE.Vector3(...position);
			const out = Math.sign(center.z);
			const lidAt = (open, sv, sign) => open * (1 - sv * sv) + 0.06 * sv * out * sign;
			const onSphere = (size, elevation, azimuth) => center.clone().add(new THREE.Vector3(Math.cos(elevation) * Math.cos(azimuth), Math.sin(elevation), Math.cos(elevation) * Math.sin(azimuth)).multiplyScalar(size));
			const lid = upper => angles(-1, 1, 16).map(sv => onSphere(1.04 * radius, upper ? lidAt(0.5, sv, 1) * (1 - eyes.lid) + (lidAt(-0.36, sv, -1) + 0.03) * eyes.lid : lidAt(-0.36, sv, -1), out * sv * 0.95));
			line(circle(center, camera.clone().sub(center).normalize(), radius, 36), BLUE_MID, 1.05, 0.35);
			line(circle(center.clone().addScaledVector(gaze, radius * 0.86), gaze, radius * 0.5, 24), BLUE, 1.05, 0.9 * seen);
			line(circle(center.clone().addScaledVector(gaze, radius * 0.97), gaze, radius * 0.2, 14), BLUE, 1.05, 0.7 * seen);
			line(lid(true), BLUE, 1.3, 0.95);
			line(lid(false), BLUE, 1.05, 0.75);
			line(angles(-1, 1, 12).map(sv => onSphere(1.32 * radius, 0.82 + brow.arch * (1 - sv * sv) - 0.06 * sv, out * (0.15 + sv * 0.62))), BLUE_MID, brow.width, 0.8);
		}
	}
}

class Motion {
	constructor() {
		this.time = 0;
		this.shift = 0;
		this.shiftTarget = 0;
		this.nextShift = 2 + Math.random() * 2;
		this.arms = { from: 'hang', to: 'hang', blend: 1, next: 9 + Math.random() * 8 };
		this.head = { nod: 0, turn: 0, tilt: 0, target: { nod: 0, turn: 0, tilt: 0 }, next: 1 };
		this.eyes = { lid: 0, nextBlink: 1.5 + Math.random() * 2, blinkAt: -1, secondBlink: false, pitch: 0, yaw: 0, toPitch: 0, toYaw: 0, nextGaze: 2 };
	}

	update(delta) {
		const { arms, head, eyes } = this;
		const time = this.time += delta;
		if (time >= this.nextShift) {
			const choices = [-1, -0.8, 0, 0.8, 1].filter(value => Math.sign(value) !== Math.sign(this.shiftTarget) || value === 0 && this.shiftTarget !== 0);
			this.shiftTarget = choices[Math.floor(Math.random() * choices.length)];
			this.nextShift = time + 5 + Math.random() * 6;
		}
		this.shift += (this.shiftTarget - this.shift) * (1 - Math.exp(-delta * 1.1));
		arms.blend = Math.min(1, arms.blend + delta / 1.6);
		if (time >= arms.next && arms.blend === 1) {
			arms.from = arms.to;
			arms.to = ['hang', 'hang', 'hang', 'hips', 'behind'][Math.floor(Math.random() * 5)];
			arms.blend = arms.from === arms.to ? 1 : 0;
			arms.next = time + (arms.to === 'hang' ? 10 + Math.random() * 10 : 5 + Math.random() * 6);
		}
		if (time >= eyes.nextBlink) {
			eyes.blinkAt = time;
			eyes.secondBlink = !eyes.secondBlink && Math.random() < 0.18;
			eyes.nextBlink = time + (eyes.secondBlink ? 0.28 : 2 + Math.random() * 4.5);
		}
		const blink = (time - eyes.blinkAt) / 0.17;
		eyes.lid = eyes.blinkAt >= 0 && blink < 1 ? Math.sin(Math.PI * blink) ** 0.7 : 0;
		if (time >= eyes.nextGaze) {
			const center = Math.random() < 0.35;
			eyes.toPitch = center ? 0 : (Math.random() * 2 - 1) * 0.55;
			eyes.toYaw = center ? 0 : (Math.random() * 2 - 1) * 0.9;
			eyes.nextGaze = time + 1.2 + Math.random() * 3.5;
			head.next = Math.min(head.next, time + 0.15);
		}
		const saccade = 1 - Math.exp(-delta * 28);
		eyes.pitch += (eyes.toPitch - eyes.pitch) * saccade;
		eyes.yaw += (eyes.toYaw - eyes.yaw) * saccade;
		if (time >= head.next) {
			head.target = { nod: (Math.random() * 2 - 1) * 0.07, turn: (Math.random() * 2 - 1) * 0.15 + 0.4 * eyes.toYaw, tilt: (Math.random() * 2 - 1) * 0.05 };
			head.next = time + 2 + Math.random() * 4;
		}
		for (const angle of ['nod', 'turn', 'tilt']) {
			head[angle] += (head.target[angle] - head[angle]) * (1 - Math.exp(-delta * 2.5));
		}
		const breath = 0.5 - 0.5 * Math.cos(2 * Math.PI * time / 4.8);
		return {
			breath,
			shift: this.shift,
			sway: 0.05 * this.shift,
			pelvisRoll: -0.1 * this.shift,
			chestRoll: 0.06 * this.shift,
			arms,
			head: { nod: head.nod - 0.02 * breath, turn: head.turn, tilt: head.tilt - 0.06 * this.shift },
			eyes,
		};
	}
}

function headSurface(rings, profile, crown) {
	const samples = 64;
	const levels = rings.map(open => {
		const y = open.reduce((sum, point) => sum + point[1], 0) / open.length;
		const [first, last] = [open[0], open.at(-1)];
		const points = Math.hypot(first[0] - last[0], first[2] - last[2]) < 1 ? open : [...open, [profile.filter(([x]) => x > 3).reduce((best, point) => Math.abs(point[1] - y) < Math.abs(best[1] - y) ? point : best)[0], y, 0]];
		const xs = points.map(([x]) => x);
		const center = (Math.max(...xs) + Math.min(...xs)) / 2;
		const radii = angles(-Math.PI, Math.PI, samples).map(angle => {
			const [dx, dz] = [Math.cos(angle), Math.sin(angle)];
			return Math.max(0, ...points.map(([x0, , z0], i) => {
				const [x1, , z1] = points[(i + 1) % points.length];
				const [ax, az, ex, ez] = [x0 - center, z0, x1 - x0, z1 - z0];
				const denominator = dx * ez - dz * ex;
				const along = (ax * ez - az * ex) / denominator;
				const edge = (ax * dz - az * dx) / denominator;
				return Math.abs(denominator) > 1e-9 && along > 0 && edge >= 0 && edge <= 1 ? along : 0;
			}));
		});
		return { y, center, radii };
	});
	const top = levels.at(-1);
	return (angle, y, lift = 0) => {
		const sample = level => {
			const j = (angle + Math.PI) / (2 * Math.PI) * samples;
			const below = clamp(Math.floor(j), 0, samples - 1);
			return lerp(level.radii[below], level.radii[below + 1], j - below);
		};
		let radius, center;
		if (y >= top.y) {
			const u = Math.min(1, (y - top.y) / (crown[1] - top.y));
			radius = sample(top) * Math.sqrt(1 - u * u);
			center = lerp(top.center, crown[0], u);
		} else {
			const above = Math.max(1, levels.findIndex(level => level.y >= y));
			const u = clamp((y - levels[above - 1].y) / (levels[above].y - levels[above - 1].y), 0, 1);
			radius = lerp(sample(levels[above - 1]), sample(levels[above]), u);
			center = lerp(levels[above - 1].center, levels[above].center, u);
		}
		return [center + (radius + lift) * Math.cos(angle), y, (radius + lift) * Math.sin(angle)];
	};
}

function shortHair(surface, k) {
	const hairline = angle => k * interpolate([[0, 20.3], [0.6, 19.6], [0.9, 18.4], [1.3, 17.2], [1.6, 15.5], [1.75, 15], [2, 15.3], [2.3, 13], [2.7, 9], [Math.PI, 6.5]], Math.abs(angle));
	const { lines, point } = hairShell(surface, k, hairline, (angle, y) => k * (0.8 + 1.8 * smoothstep(y, 15 * k, 23 * k) + 0.4 * Math.sin(7 * angle + 1) * smoothstep(y, 19 * k, 23 * k)));
	for (const angle of angles(-Math.PI, Math.PI, 8).slice(1)) {
		lines.push({ alpha: 0.6, points: angles(hairline(angle), 23.4 * k, 12).map(y => point(angle, Math.min(y, 23.14 * k))) });
	}
	return lines;
}

function tiedHair(surface, k) {
	const hairline = angle => k * interpolate([[0, 19.8], [0.6, 19.4], [0.9, 18.6], [1.3, 16.8], [1.6, 14.8], [1.9, 14], [2.3, 12.5], [2.7, 9.5], [Math.PI, 8.5]], Math.abs(angle));
	const crown = 23.14 * k;
	const knot = 11 * k;
	const { lines, point } = hairShell(surface, k, hairline, (angle, y) => k * (0.9 + 1.6 * smoothstep(y, 16 * k, 23 * k)));
	const over = angle => angles(hairline(angle), crown, 10).map(y => point(angle, y));
	lines.push({ alpha: 0.85, points: [...over(0.03), ...over(Math.PI).reverse().slice(1)] });
	lines.push({ alpha: 0.85, points: [...over(-Math.PI / 2), ...over(Math.PI / 2).reverse().slice(1)] });
	for (const start of [0.45, 1.1, 1.7, 2.3]) {
		for (const side of [-1, 1]) {
			const peak = Math.max(0, crown - 0.8 * k - hairline(start)) * Math.max(0, 1 - start / 2.2);
			lines.push({ alpha: 0.7, points: angles(0, 1, 16).map(t => point(side * (start + (Math.PI - 0.05 - start) * t), lerp(hairline(start), knot, t) + peak * Math.sin(Math.PI * t))) });
		}
	}
	const tie = new THREE.Vector3(...point(Math.PI, knot)).add(new THREE.Vector3(-0.8 * k, 0, 0));
	for (const spread of [-1, 0, 1]) {
		lines.push({ alpha: 0.6, points: new THREE.CubicBezierCurve3(tie, tie.clone().add(new THREE.Vector3(-2.5 * k, -3 * k, spread * 1.2 * k)), new THREE.Vector3(-12 * k, -6 * k, spread * 1.8 * k), new THREE.Vector3(-12.5 * k, -14 * k, spread * 0.8 * k)).getPoints(14).map(vector => vector.toArray()) });
	}
	lines.push({ alpha: 0.75, closed: true, points: angles(0, 2 * Math.PI, 12).slice(1).map(angle => tie.clone().add(new THREE.Vector3(-0.4 * k * Math.cos(angle), 0.9 * k * Math.sin(angle), 1.1 * k * Math.cos(angle))).toArray()) });
	return lines.map(line => ({ ...line, width: 1.3 }));
}

function hairShell(surface, k, hairline, lift) {
	const center = new THREE.Vector3((surface(0, 13 * k)[0] + surface(Math.PI, 13 * k)[0]) / 2, 12.5 * k, 0);
	const point = (angle, y) => {
		const skin = new THREE.Vector3(...surface(angle, y));
		return skin.addScaledVector(skin.clone().sub(center).normalize(), lift(angle, y)).toArray();
	};
	const around = angles(-Math.PI, Math.PI, 96);
	const lines = [{ alpha: 0.75, points: around.map(angle => surface(angle, hairline(angle), 0.3 * k)) }];
	for (const y of [22, 18.1, 14.2, 10.3, 6.9].map(y => y * k)) {
		for (const run of runs(around, angle => y > hairline(angle))) {
			lines.push({ alpha: 0.6, points: run.map(angle => point(angle, y)) });
		}
	}
	return { lines, point };
}

function beard(surface, k) {
	const side = 1.62;
	const top = angle => k * interpolate([[0, 9.9], [0.25, 9.7], [0.45, 9.9], [0.75, 10.8], [1.1, 12.3], [1.4, 14], [side, 15.4]], Math.abs(angle));
	const bottom = angle => k * interpolate([[0, 1], [0.4, 1.5], [0.8, 2.8], [1.2, 5.5], [side, 8.5]], Math.abs(angle));
	const mouthTop = angle => k * (8.4 - 0.4 * (angle / 0.42) ** 2);
	const mouthBottom = angle => k * (6.7 + 0.5 * (angle / 0.42) ** 2);
	const inMouth = (angle, y) => Math.abs(angle) < 0.42 && y > mouthBottom(angle) && y < mouthTop(angle);
	const point = (angle, y) => surface(angle, y, 0.6 * k);
	const across = angles(-side, side, 48);
	const lines = [
		{ alpha: 0.75, points: [...across.map(angle => point(angle, top(angle))), ...angles(top(side), bottom(side), 6).map(y => point(side, y)), ...across.toReversed().map(angle => point(angle, bottom(angle))), ...angles(bottom(-side), top(-side), 6).map(y => point(-side, y))] },
		{ alpha: 0.75, closed: true, points: [...angles(-0.42, 0.42, 12).map(angle => point(angle, mouthTop(angle))), ...angles(0.42, -0.42, 12).map(angle => point(angle, mouthBottom(angle)))] },
	];
	for (const y of [1.5, 5.4, 9.3, 13.2].map(y => y * k)) {
		for (const run of runs(across, angle => y > bottom(angle) && y < top(angle) && !inMouth(angle, y))) {
			lines.push({ alpha: 0.5, points: run.map(angle => point(angle, y)) });
		}
	}
	for (const angle of [-0.8, 0, 0.8]) {
		for (const run of runs(angles(bottom(angle), top(angle), 16), y => !inMouth(angle, y))) {
			lines.push({ alpha: 0.5, points: run.map(y => point(angle, y)) });
		}
	}
	return lines;
}

function compose(weight, height, age, sex, impedances) {
	const f32 = Math.fround;
	const meters = f32(height / 100);
	const healthyWeight = f32(f32(meters * meters) * (sex === 'male' ? 22 : 21));
	const [fatShare, leanShare] = sex === 'male' ? [0.15, 0.85] : [0.23, 0.77];
	const standardFat = roundTenth(f32(fatShare * healthyWeight));
	const standardLean = roundTenth(f32(leanShare * healthyWeight));
	const low = Object.fromEntries(SEGMENTS.map((segment, i) => [segment, impedances[i]]));
	const high = Object.fromEntries(SEGMENTS.map((segment, i) => [segment, impedances[i + 5]]));
	if (height < 100 || height > 260 || weight < 20 || weight > 260 || low.trunk < 1 || high.trunk < 1 || SEGMENTS.slice(1).some(limb => low[limb] < 100 || high[limb] < 100)) {
		return null;
	}
	high.trunk = high.trunk > low.trunk ? fusedMultiplyAdd(low.trunk, 0.826, -3) : high.trunk * 0.826;
	low.trunk *= 0.826;
	if (low.trunk < 0 || high.trunk < 0) {
		return null;
	}
	const whole = side => side.trunk + side.leftArm * side.rightArm / (side.leftArm + side.rightArm) + side.leftLeg * side.rightLeg / (side.leftLeg + side.rightLeg);
	const lowSum = low.leftArm + low.rightArm + low.trunk + low.rightLeg + low.leftLeg;
	const highSum = high.leftArm + high.rightArm + high.trunk + high.rightLeg + high.leftLeg;
	const index = lowSum / height;
	const bmi = weight / height / height * 100 * 100;
	let fat = f32(regression(COMPOSITION.fatMass[index < 9 ? 0 : index < 12 ? 1 : 2], [
		weight, weight * weight, height * height, height, bmi,
		low.rightArm - high.rightArm, low.leftArm - high.leftArm, low.trunk - high.trunk, low.rightLeg - high.rightLeg, low.leftLeg - high.leftLeg,
		whole(low) / height / height, whole(high) / height / height,
		low.rightArm / high.rightArm, low.leftArm / high.leftArm, low.trunk / high.trunk, low.leftLeg / high.leftLeg, low.rightLeg / high.rightLeg,
		low.rightArm * bmi, low.leftArm * bmi, low.trunk * bmi, low.rightLeg * bmi, low.leftLeg * bmi, high.trunk * bmi, high.rightLeg * bmi, high.leftLeg * bmi,
		index / height, highSum / height / height,
	]));
	let fatPercent = fat / weight * 100;
	if (fatPercent < 3 || fatPercent > 60) {
		fatPercent = fatPercent < 3 ? 3 : 60;
		fat = weight * fatPercent / 100;
	}
	const fatMass = roundTenth(fat);
	fatPercent = roundTenth(fatPercent);
	const leanMass = weight - fatMass;
	const waterMass = leanMass * 0.733;
	const proteinMass = leanMass * 0.2;
	let fats = Object.fromEntries(SEGMENTS.map(segment => [segment, regression(COMPOSITION.fat[segment], [low[segment], fatMass, high[segment]])]));
	for (const [left, right, tolerance] of [['leftArm', 'rightArm', 0.3], ['leftLeg', 'rightLeg', 0.5]]) {
		if (Math.abs(fats[right] - fats[left]) > tolerance) {
			const [smaller, larger] = fats[right] <= fats[left] ? [right, left] : [left, right];
			const step = (low[smaller] + high[smaller]) / 20213;
			fats = { ...fats, [smaller]: fats[larger] + (low[smaller] > low[larger] ? -step : step) };
		}
	}
	const segments = Object.fromEntries(SEGMENTS.map(segment => {
		const margin = (low[segment] + high[segment]) / COMPOSITION.floorDivisors[segment];
		const segmentFat = fats[segment] < 0.1 ? margin + 0.1 : fats[segment];
		const muscle = regression(COMPOSITION.muscle[segment], [low[segment], leanMass, high[segment]]);
		const segmentMuscle = muscle < COMPOSITION.muscleFloors[segment] ? margin + COMPOSITION.muscleFloors[segment] : muscle;
		return [segment, {
			fatMass: segmentFat,
			fat: segmentFat / regression(COMPOSITION.fatReference[segment], [height, standardFat]) * 100,
			muscleMass: segmentMuscle,
			muscle: segmentMuscle / regression(COMPOSITION.muscleReference[segment], [height, standardLean, weight]) * 100,
		}];
	}));
	const [leftArm, rightArm, leftLeg, rightLeg] = ['leftArm', 'rightArm', 'leftLeg', 'rightLeg'].map(limb => f32(segments[limb].muscle));
	const asymmetry = (left, right, mild, severe) => {
		const difference = Math.abs(f32(left - right));
		return difference >= severe ? 2 : difference >= mild ? 1 : 0;
	};
	const band = (left, right, width) => toInt32(f32(f32(f32(f32(left + right) * 0.5) - 100) / width));
	const bfr = roundTenth(fatPercent);
	const fatToLose = weight * bfr / 100;
	return {
		values: {
			bfr,
			fatMass: fatToLose,
			leanMass: weight - fatToLose,
			weightTarget: weight + (Math.max(roundTenthFromDouble(fatToLose - weight + standardLean), 0) + roundTenthFromDouble(standardFat - fatToLose)),
			weightStandard: roundTenth(healthyWeight),
			age: age < 10 ? age : age + (COMPOSITION.ageOffsets[sex].find(([limit]) => fatPercent < limit)?.[1] ?? 5),
			bmr: toInt32(fusedMultiplyAdd(leanMass, 21.6, 370)),
			muscle: roundTenth((waterMass + proteinMass) / weight * 100),
			bmi: roundTenth(weight * 10000 / f32(height * height)),
			proteinMass: weight * roundTenth(proteinMass / weight * 100) / 100,
			waterMass: weight * roundTenth(waterMass / weight * 100) / 100,
			muscleMass: weight * roundTenth((waterMass + proteinMass) / weight * 100) / 100,
			water: roundTenth(waterMass / weight * 100),
			smi: roundTenth((segments.rightArm.muscleMass + segments.leftArm.muscleMass + segments.rightLeg.muscleMass + segments.leftLeg.muscleMass) / (height * height / 10000)),
			sm: roundTenth(fusedMultiplyAdd(waterMass, 0.834, -2.627) / weight * 100),
			armBalance: asymmetry(leftArm, rightArm, 6, 10),
			legBalance: asymmetry(leftLeg, rightLeg, 3, 5),
			armAndLegBalance: Math.min(Math.abs(band(leftArm, rightArm, 15) - band(leftLeg, rightLeg, 10)), 2),
			protein: roundTenth(proteinMass / weight * 100),
		},
		segments,
	};
}

function regression([intercept, coefficients], features) {
	return features.slice(1).reduce((total, feature, i) => fusedMultiplyAdd(feature, coefficients[i + 1], total), features[0] * coefficients[0]) + intercept;
}

function roundTenth(value) {
	return roundFraction(value, Math.fround(value) % 1, 10);
}

function roundTenthFromDouble(value) {
	return roundFraction(value, Math.fround(value % 1), 10);
}

function roundFraction(value, fraction, scale) {
	const f32 = Math.fround;
	let scaled = f32(fraction * scale);
	if (scaled % 1 > 0.5) {
		scaled = f32(scaled + 1);
	}
	const part = f32(f32(toInt32(scaled)) / scale);
	return f32((part === 0 && f32(value - toInt32(value)) > 0.99 ? 1 : part) + f32(toInt32(value)));
}

function toInt32(value) {
	return Number.isNaN(value) ? 0 : Math.trunc(Math.min(Math.max(value, -2147483648), 2147483647));
}

function fusedMultiplyAdd(left, right, addend) {
	const view = new DataView(new ArrayBuffer(8));
	const [a, b, c] = [left, right, addend].map(value => {
		view.setFloat64(0, value);
		const bits = view.getBigUint64(0);
		const exponent = Number((bits >> 52n) & 0x7ffn);
		const mantissa = (bits & 0xfffffffffffffn) | (exponent ? 1n << 52n : 0n);
		return { mantissa: bits >> 63n ? -mantissa : mantissa, exponent: Math.max(exponent, 1) - 1075 };
	});
	if (!a.mantissa || !b.mantissa || !c.mantissa) {
		return left * right + addend;
	}
	const exponent = Math.min(a.exponent + b.exponent, c.exponent);
	return Number((a.mantissa * b.mantissa << BigInt(a.exponent + b.exponent - exponent)) + (c.mantissa << BigInt(c.exponent - exponent))) * 2 ** exponent;
}

function interpolate(table, x) {
	const above = table.findIndex(([at]) => at >= x);
	if (above <= 0) {
		return table[Math.max(0, above)][1];
	}
	const [[x0, y0], [x1, y1]] = [table[above - 1], table[above]];
	return y0 + (y1 - y0) * (x - x0) / (x1 - x0);
}

function runs(samples, keep) {
	const result = [[]];
	for (const sample of samples) {
		if (keep(sample)) {
			result.at(-1).push(sample);
		} else if (result.at(-1).length) {
			result.push([]);
		}
	}
	return result.filter(run => run.length > 1);
}

function angles(from, to, count) {
	return Array.from({ length: count + 1 }, (_, i) => from + (to - from) * i / count);
}

function meridian(rings, index) {
	return rings.map(ring => ring[index]);
}

function rounding(distance, cap) {
	return cap ? Math.sqrt(Math.max(0, 1 - (Math.max(0, cap - distance) / cap) ** 2)) : 1;
}

function bump(value, center, width) {
	return Math.max(0, 1 - ((value - center) / width) ** 2);
}

function reach(start, goal, upper, lower, pole) {
	const toGoal = goal.clone().sub(start);
	const length = Math.min(toGoal.length(), (upper + lower) * 0.999);
	const direction = toGoal.normalize();
	const along = (upper * upper + length * length - lower * lower) / (2 * length);
	const bend = pole.clone().addScaledVector(direction, -pole.dot(direction)).normalize();
	return [
		start.clone().addScaledVector(direction, along).addScaledVector(bend, Math.sqrt(Math.max(0, upper * upper - along * along))),
		start.clone().addScaledVector(direction, length),
	];
}

function outline(center, forward, across, depth, width) {
	return angles(0, 2 * Math.PI, 28).slice(0, -1).map(angle => {
		const along = depth * Math.sign(Math.cos(angle)) * Math.abs(Math.cos(angle)) ** (2 / EXPONENT);
		const sideways = width * Math.sign(Math.sin(angle)) * Math.abs(Math.sin(angle)) ** (2 / EXPONENT);
		return center.clone().addScaledVector(forward, along).addScaledVector(across, sideways);
	});
}

class Beams {
	constructor() {
		this.group = new THREE.Group();
		this.batches = new Map([0.85, 1.05, 1.3, 1.6].map(linewidth => {
			const positions = new Float32Array(4096 * 6);
			const colors = new Float32Array(4096 * 6);
			const buffers = [positions, colors].map(array => new THREE.InstancedInterleavedBuffer(array, 6, 1).setUsage(THREE.DynamicDrawUsage));
			const geometry = new THREE.LineSegmentsGeometry();
			geometry.setAttribute('instanceStart', new THREE.InterleavedBufferAttribute(buffers[0], 3, 0));
			geometry.setAttribute('instanceEnd', new THREE.InterleavedBufferAttribute(buffers[0], 3, 3));
			geometry.setAttribute('instanceColorStart', new THREE.InterleavedBufferAttribute(buffers[1], 3, 0));
			geometry.setAttribute('instanceColorEnd', new THREE.InterleavedBufferAttribute(buffers[1], 3, 3));
			const mesh = new THREE.LineSegments2(geometry, new THREE.LineMaterial({ linewidth, vertexColors: true, transparent: true, toneMapped: false, blending: THREE.CustomBlending, blendEquation: THREE.MaxEquation, fog: true }));
			mesh.frustumCulled = false;
			this.group.add(mesh);
			return [linewidth, { mesh, positions, colors, buffers, count: 0 }];
		}));
	}

	setResolution(width, height) {
		for (const { mesh } of this.batches.values()) {
			mesh.material.resolution.set(width, height);
		}
	}

	begin() {
		for (const batch of this.batches.values()) {
			batch.count = 0;
		}
	}

	end() {
		for (const { mesh, buffers, count } of this.batches.values()) {
			mesh.geometry.instanceCount = count;
			for (const buffer of buffers) {
				buffer.clearUpdateRanges();
				buffer.addUpdateRange(0, count * 6);
				buffer.needsUpdate = true;
			}
		}
	}

	line(points, color, width, alpha = 1, close = false) {
		const batch = this.batches.get(width);
		const value = parseInt(color.slice(1), 16);
		const rgb = [16, 8, 0].map(shift => ((value >> shift) & 255) / 255 * alpha);
		const ends = close ? [...points, points[0]] : points;
		for (let i = 1; i < ends.length; i++) {
			ends[i - 1].toArray(batch.positions, 6 * batch.count);
			ends[i].toArray(batch.positions, 6 * batch.count + 3);
			batch.colors.set(rgb, 6 * batch.count);
			batch.colors.set(rgb, 6 * batch.count++ + 3);
		}
	}
}

class Volume {
	constructor() {
		this.positions = new Float32Array(3 * 131072);
		this.mesh = new THREE.Mesh(new THREE.BufferGeometry().setAttribute('position', new THREE.BufferAttribute(this.positions, 3).setUsage(THREE.DynamicDrawUsage)), new THREE.MeshBasicMaterial({ color: 0x000000, transparent: true, opacity: 0.15, depthWrite: false, side: THREE.DoubleSide, polygonOffset: true, polygonOffsetFactor: 1, polygonOffsetUnits: 4 }));
		this.mesh.frustumCulled = false;
		this.mesh.renderOrder = 1;
		this.count = 0;
	}

	tube(rings) {
		for (let i = 1; i < rings.length; i++) {
			const [below, above] = [rings[i - 1], rings[i]];
			below.forEach((point, j) => {
				const next = (j + 1) % below.length;
				for (const corner of [point, below[next], above[next], point, above[next], above[j]]) {
					corner.toArray(this.positions, 3 * this.count++);
				}
			});
		}
	}

	end() {
		const position = this.mesh.geometry.attributes.position;
		this.mesh.geometry.setDrawRange(0, this.count);
		position.clearUpdateRanges();
		position.addUpdateRange(0, 3 * this.count);
		position.needsUpdate = true;
	}
}

render();
</script>
</body>
</html>
