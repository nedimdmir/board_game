/**
 * Standalone Offline QR Code SVG Generator
 * Based on Kazuhiko Arase's QR Code algorithm (Public Domain / MIT)
 * Zero external libraries, zero CDN, 100% offline pure SVG generation.
 */
(function(window) {
  'use strict';

  // Galois Field and Reed-Solomon tables
  var QRMath = {
    glog: function(n) {
      if (n < 1) throw new Error('glog(' + n + ')');
      return QRMath.LOG_TABLE[n];
    },
    gexp: function(n) {
      while (n < 0) n += 255;
      while (n >= 256) n -= 255;
      return QRMath.EXP_TABLE[n];
    },
    EXP_TABLE: new Array(256),
    LOG_TABLE: new Array(256)
  };

  for (var i = 0; i < 8; i++) QRMath.EXP_TABLE[i] = 1 << i;
  for (var i = 8; i < 256; i++) QRMath.EXP_TABLE[i] = QRMath.EXP_TABLE[i - 4] ^ QRMath.EXP_TABLE[i - 5] ^ QRMath.EXP_TABLE[i - 6] ^ QRMath.EXP_TABLE[i - 8];
  for (var i = 0; i < 255; i++) QRMath.LOG_TABLE[QRMath.EXP_TABLE[i]] = i;

  function QRPolynomial(num, shift) {
    if (num.length === undefined) throw new Error(num.length + '/' + shift);
    var offset = 0;
    while (offset < num.length && num[offset] === 0) offset++;
    this.num = new Array(num.length - offset + shift);
    for (var i = 0; i < num.length - offset; i++) this.num[i] = num[i + offset];
    for (var i = num.length - offset; i < this.num.length; i++) this.num[i] = 0;
  }

  QRPolynomial.prototype = {
    get: function(index) { return this.num[index]; },
    getLength: function() { return this.num.length; },
    multiply: function(e) {
      var num = new Array(this.getLength() + e.getLength() - 1);
      for (var i = 0; i < this.getLength(); i++) {
        for (var j = 0; j < e.getLength(); j++) {
          num[i + j] ^= QRMath.gexp(QRMath.glog(this.get(i)) + QRMath.glog(e.get(j)));
        }
      }
      return new QRPolynomial(num, 0);
    },
    mod: function(e) {
      if (this.getLength() - e.getLength() < 0) return this;
      var ratio = QRMath.glog(this.get(0)) - QRMath.glog(e.get(0));
      var num = new Array(this.getLength());
      for (var i = 0; i < this.getLength(); i++) num[i] = this.get(i);
      for (var i = 0; i < e.getLength(); i++) {
        num[i] ^= QRMath.gexp(QRMath.glog(e.get(i)) + ratio);
      }
      return new QRPolynomial(num, 0).mod(e);
    }
  };

  // QR RS Blocks table for versions 1 to 10 (ECC Level M)
  var RS_BLOCK_TABLE = [
    [1, 26, 16], // v1
    [1, 44, 28], // v2
    [1, 70, 44], // v3
    [2, 50, 32], // v4
    [2, 67, 43], // v5
    [4, 43, 27], // v6
    [4, 53, 31], // v7
    [4, 65, 39], // v8
    [5, 68, 40], // v9
    [5, 78, 46]  // v10
  ];

  function QRCodeModel(typeNumber) {
    this.typeNumber = typeNumber;
    this.modules = null;
    this.moduleCount = 0;
    this.dataList = [];
  }

  QRCodeModel.prototype = {
    addData: function(data) {
      this.dataList.push(data);
    },
    isDark: function(row, col) {
      if (row < 0 || this.moduleCount <= row || col < 0 || this.moduleCount <= col) {
        throw new Error(row + ',' + col);
      }
      return this.modules[row][col];
    },
    getModuleCount: function() {
      return this.moduleCount;
    },
    make: function() {
      this.moduleCount = this.typeNumber * 4 + 17;
      this.modules = new Array(this.moduleCount);
      for (var row = 0; row < this.moduleCount; row++) {
        this.modules[row] = new Array(this.moduleCount);
        for (var col = 0; col < this.moduleCount; col++) {
          this.modules[row][col] = null;
        }
      }
      this.setupPositionProbePattern(0, 0);
      this.setupPositionProbePattern(this.moduleCount - 7, 0);
      this.setupPositionProbePattern(0, this.moduleCount - 7);
      this.setupTimingPattern();
      if (this.typeNumber >= 2) {
        this.setupPositionAdjustPattern();
      }
      this.setupTypeInfo(false, 0);
      this.mapData(this.createData(), 0);
    },
    setupPositionProbePattern: function(row, col) {
      for (var r = -1; r <= 7; r++) {
        if (row + r <= -1 || this.moduleCount <= row + r) continue;
        for (var c = -1; c <= 7; c++) {
          if (col + c <= -1 || this.moduleCount <= col + c) continue;
          if ((0 <= r && r <= 6 && (c === 0 || c === 6)) ||
              (0 <= c && c <= 6 && (r === 0 || r === 6)) ||
              (2 <= r && r <= 4 && 2 <= c && c <= 4)) {
            this.modules[row + r][col + c] = true;
          } else {
            this.modules[row + r][col + c] = false;
          }
        }
      }
    },
    setupTimingPattern: function() {
      for (var r = 8; r < this.moduleCount - 8; r++) {
        if (this.modules[r][6] !== null) continue;
        this.modules[r][6] = (r % 2 === 0);
      }
      for (var c = 8; c < this.moduleCount - 8; c++) {
        if (this.modules[6][c] !== null) continue;
        this.modules[6][c] = (c % 2 === 0);
      }
    },
    setupPositionAdjustPattern: function() {
      var pos = [6, 18, 22, 26, 30, 34][this.typeNumber - 1] || (this.moduleCount - 7);
      var row = pos, col = pos;
      for (var r = -2; r <= 2; r++) {
        for (var c = -2; c <= 2; c++) {
          if (r === -2 || r === 2 || c === -2 || c === 2 || (r === 0 && c === 0)) {
            this.modules[row + r][col + c] = true;
          } else {
            this.modules[row + r][col + c] = false;
          }
        }
      }
    },
    setupTypeInfo: function(test, maskPattern) {
      var data = (1 << 3) | maskPattern; // Level M = 0
      var bits = (data << 10);
      var g = 1335;
      while (bits >= 1024) {
        var shift = Math.floor(Math.log(bits) / Math.LN2) - Math.floor(Math.log(g) / Math.LN2);
        bits ^= (g << shift);
      }
      var format = ((data << 10) | bits) ^ 21522;
      for (var i = 0; i < 15; i++) {
        var mod = (!test && ((format >> i) & 1) === 1);
        if (i < 6) this.modules[i][8] = mod;
        else if (i < 8) this.modules[i + 1][8] = mod;
        else this.modules[this.moduleCount - 15 + i][8] = mod;

        if (i < 8) this.modules[8][this.moduleCount - i - 1] = mod;
        else if (i < 9) this.modules[8][15 - i - 1 + 1] = mod;
        else this.modules[8][15 - i - 1] = mod;
      }
      this.modules[this.moduleCount - 8][8] = !test;
    },
    mapData: function(data, maskPattern) {
      var inc = -1;
      var row = this.moduleCount - 1;
      var bitIndex = 7;
      var byteIndex = 0;
      for (var col = this.moduleCount - 1; col > 0; col -= 2) {
        if (col === 6) col--;
        while (true) {
          for (var c = 0; c < 2; c++) {
            if (this.modules[row][col - c] === null) {
              var dark = false;
              if (byteIndex < data.length) {
                dark = (((data[byteIndex] >>> bitIndex) & 1) === 1);
              }
              var mask = ((row + col - c) % 2 === 0);
              if (mask) dark = !dark;
              this.modules[row][col - c] = dark;
              bitIndex--;
              if (bitIndex === -1) {
                byteIndex++;
                bitIndex = 7;
              }
            }
          }
          row += inc;
          if (row < 0 || this.moduleCount <= row) {
            row -= inc;
            inc = -inc;
            break;
          }
        }
      }
    },
    createData: function() {
      var rsBlock = RS_BLOCK_TABLE[this.typeNumber - 1];
      var totalDataCount = rsBlock[0] * rsBlock[2];
      var buffer = [];
      // 8-bit byte mode indicator (0100)
      buffer.push(4);
      var text = this.dataList.join('');
      var utf8Bytes = [];
      for (var i = 0; i < text.length; i++) {
        var code = text.charCodeAt(i);
        if (code < 128) utf8Bytes.push(code);
        else if (code < 2048) utf8Bytes.push(192 | (code >> 6), 128 | (code & 63));
        else utf8Bytes.push(224 | (code >> 12), 128 | ((code >> 6) & 63), 128 | (code & 63));
      }
      
      // Length
      var bits = [];
      function put(val, len) {
        for (var b = len - 1; b >= 0; b--) {
          bits.push((val >>> b) & 1);
        }
      }
      put(4, 4); // mode
      put(utf8Bytes.length, this.typeNumber < 10 ? 8 : 16); // char count
      for (var i = 0; i < utf8Bytes.length; i++) {
        put(utf8Bytes[i], 8);
      }
      
      // Terminate
      while (bits.length % 8 !== 0) bits.push(0);
      var bytes = [];
      for (var i = 0; i < bits.length; i += 8) {
        var b = 0;
        for (var j = 0; j < 8; j++) b = (b << 1) | bits[i + j];
        bytes.push(b);
      }

      // Pad bytes
      var pad = [236, 17];
      var pidx = 0;
      while (bytes.length < totalDataCount) {
        bytes.push(pad[pidx]);
        pidx = (pidx + 1) % 2;
      }

      // Error correction generator polynomial
      var ecCount = rsBlock[1] - rsBlock[2];
      var gpoly = new QRPolynomial([1], 0);
      for (var i = 0; i < ecCount; i++) {
        gpoly = gpoly.multiply(new QRPolynomial([1, QRMath.gexp(i)], 0));
      }

      var rawPoly = new QRPolynomial(bytes, gpoly.getLength() - 1);
      var modPoly = rawPoly.mod(gpoly);
      var ecBytes = [];
      for (var i = 0; i < gpoly.getLength() - 1; i++) {
        var modIndex = i + modPoly.getLength() - (gpoly.getLength() - 1);
        ecBytes.push(modIndex >= 0 ? modPoly.get(modIndex) : 0);
      }

      return bytes.concat(ecBytes);
    }
  };

  /**
   * Generates a crisp, scalable, standalone SVG string for any URL or text.
   */
  function generateQRCodeSVG(text, size) {
    size = size || 180;
    var len = text.length;
    var type = 3;
    if (len < 14) type = 1;
    else if (len < 26) type = 2;
    else if (len < 42) type = 3;
    else if (len < 62) type = 4;
    else if (len < 84) type = 5;
    else type = 7;

    var qr = new QRCodeModel(type);
    qr.addData(text);
    qr.make();

    var count = qr.getModuleCount();
    var margin = 2;
    var fullSize = count + margin * 2;
    var path = '';

    for (var r = 0; r < count; r++) {
      for (var c = 0; c < count; c++) {
        if (qr.isDark(r, c)) {
          path += 'M' + (c + margin) + ',' + (r + margin) + 'h1v1h-1z ';
        }
      }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + fullSize + ' ' + fullSize + '" width="' + size + '" height="' + size + '" shape-rendering="crispEdges">' +
           '<rect width="100%" height="100%" fill="#FFFFFF" rx="3"/>' +
           '<path d="' + path + '" fill="#0F172A"/>' +
           '</svg>';
  }

  window.generateQRCodeSVG = generateQRCodeSVG;
})(typeof window !== 'undefined' ? window : global);
