jest.mock('fs');

const fs = require('fs');
const {deriveDarkSvg, distinctColors, COLORS, main} = require('../../util/derive-dark-diagram');

// A minimal light SVG exercising every colour form the real diagrams use: the
// root background, white fills, black in both a presentation attribute and an
// inline stroke style, the transparent lifeline hitbox, fill="none" borders,
// and each pastel package fill.
const LIGHT = [
  '<svg xmlns="http://www.w3.org/2000/svg" width="100px" height="50px" viewBox="0 0 100 50" style="width:100px;height:50px;background:#FFFFFF;">',
  '<rect fill="#FFFFFF" style="stroke:#000000;stroke-width:1;" width="10" height="10" x="1" y="1"/>',
  '<rect fill="#000000" fill-opacity="0.00000" width="8" height="20" x="0" y="0"/>',
  '<rect fill="none" style="stroke:#000000;" width="5" height="5" x="2" y="2"/>',
  '<path fill="#F0F4C3"/>',
  '<path fill="#E3F2FD"/>',
  '<path fill="#FFF3E0"/>',
  '<path fill="#E8F5E9"/>',
  '<path fill="#F3E5F5"/>',
  '<path fill="#ECEFF1"/>',
  '<path fill="#FFEBEE"/>',
  '<text fill="#000000" x="3" y="9">Engine</text>',
  '</svg>',
].join('');

const PASTELS = Object.entries(COLORS).filter(([from]) => from !== '#FFFFFF' && from !== '#000000');

describe('deriveDarkSvg', () => {
  describe('palette', () => {
    test('drops the solid page background rather than surfacing it', () => {
      const dark = deriveDarkSvg(LIGHT);

      expect(dark).toContain('background:transparent;');
      expect(dark).not.toContain('background:#FFFFFF');
    });

    test('maps black text and strokes to light grey', () => {
      const dark = deriveDarkSvg(LIGHT);

      expect(dark).toContain('fill="#e3e3e3"');
      expect(dark).toContain('stroke:#e3e3e3');
    });

    test('maps white fills to the raised surface', () => {
      const dark = deriveDarkSvg(LIGHT);

      expect(dark).toContain('fill="#3b3b3d"');
    });

    test.each(PASTELS)('maps pastel %s to %s', (from, to) => {
      const dark = deriveDarkSvg(LIGHT);

      expect(dark).toContain(`fill="${to}"`);
      expect(dark).not.toContain(from);
    });

    test('leaves no source colour behind', () => {
      const dark = deriveDarkSvg(LIGHT);

      for (const source of Object.keys(COLORS)) {
        expect(dark).not.toContain(source);
      }
    });
  });

  describe('structure preserved', () => {
    test('keeps geometry, borders and text untouched', () => {
      const dark = deriveDarkSvg(LIGHT);

      expect(dark).toContain('viewBox="0 0 100 50"');
      expect(dark).toContain('width="100px"');
      expect(dark).toContain('fill="none"');
      expect(dark).toContain('<text fill="#e3e3e3" x="3" y="9">Engine</text>');
    });

    test('keeps the transparent hitbox transparent', () => {
      const dark = deriveDarkSvg(LIGHT);

      expect(dark).toContain('fill="#e3e3e3" fill-opacity="0.00000"');
    });
  });

  describe('guards', () => {
    test('throws on a colour outside the palette', () => {
      const stray = LIGHT.replace('fill="#F0F4C3"', 'fill="#ABCDEF"');

      expect(() => deriveDarkSvg(stray)).toThrow('#ABCDEF');
    });

    test('refuses an already-dark SVG (its own output)', () => {
      const dark = deriveDarkSvg(LIGHT);

      expect(() => deriveDarkSvg(dark)).toThrow(/Unmapped/);
    });

    test('survives a future lower-cased render', () => {
      const dark = deriveDarkSvg(LIGHT.toLowerCase());

      expect(dark).toContain('fill="#3b3b3d"');
      expect(dark).toContain('background:transparent;');
    });
  });

  describe('distinctColors', () => {
    test('returns distinct upper-cased 6-digit hex', () => {
      expect(distinctColors('a #abc123 b #ABC123 c #ffffff')).toEqual(['#ABC123', '#FFFFFF']);
    });

    test('ignores element id references and short hex', () => {
      expect(distinctColors('href="#ent0001" x="#ab" y="#abcd"')).toEqual([]);
    });
  });

  describe('cli', () => {
    beforeEach(() => {
      jest.spyOn(console, 'log').mockImplementation(() => {});
      jest.spyOn(console, 'error').mockImplementation(() => {});
      fs.readFileSync.mockReset();
      fs.writeFileSync.mockReset();
    });

    afterEach(() => {
      jest.restoreAllMocks();
      delete process.env.SCRIPT_QUIET;
    });

    test('writes a -dark sibling next to each input', () => {
      fs.readFileSync.mockReturnValue(LIGHT);

      main(['a/architecture.svg']);

      expect(fs.writeFileSync).toHaveBeenCalledWith('a/architecture-dark.svg', expect.stringContaining('background:transparent'), 'utf8');
    });

    test('skips inputs that are already dark variants', () => {
      main(['a/architecture-dark.svg']);

      expect(fs.readFileSync).not.toHaveBeenCalled();
      expect(fs.writeFileSync).not.toHaveBeenCalled();
    });

    test('stays silent under SCRIPT_QUIET', () => {
      process.env.SCRIPT_QUIET = '1';
      fs.readFileSync.mockReturnValue(LIGHT);

      main(['a/architecture.svg']);

      expect(console.log).not.toHaveBeenCalled();
    });

    test('exits non-zero with usage when given no inputs', () => {
      jest.spyOn(process, 'exit').mockImplementation((code) => {
        throw new Error(`exit ${code}`);
      });

      expect(() => main([])).toThrow('exit 1');
      expect(console.log).toHaveBeenCalledWith(expect.stringContaining('Usage:'));
    });

    test('exits zero on --help', () => {
      jest.spyOn(process, 'exit').mockImplementation((code) => {
        throw new Error(`exit ${code}`);
      });

      expect(() => main(['--help'])).toThrow('exit 0');
    });
  });
});
