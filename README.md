WavInfoPHP
==========

PHP Class for reading basic info of PCM WAV files

Only PCM WAV files are supported. May have problems with bit resolutions not multiple of 8 like 20 bit pcm.

$wavinfo=new WavInfo($filename);

Public methods:

 - getSampleRate() returns the sample rate in Hz
 - getChannels() Returns the cannel count (1: Mono, 2: Stereo.)
 - getBits() returns the sample resolution (example: cd quality uses 16 bits)
 - getSamples() returns the number of Samples in the WAV file. Here Sample means a block of one sample for each channel.
 - getDuration() returns the duration of the audio in the WAV file in seconds
 - describe() returns a string: simple textual description of the WAV file

 - Why?
 - At the time of writing I could not find a simple PHP class on teh mighty net that attempted to get the above info from WAV files in a correct way (parsing chunks correctly).

