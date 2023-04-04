JAVAC=javac
sources = $(wildcard *.java)
classes = $(sources:.java=.class)

all: jar

clean:
	rm -f *.class

%.class: %.java
	$(JAVAC) $<

jar: $(classes)
	@echo "Manifest-Version: 1.0" > manifest.txt
	@echo "Class-Path: ." >> manifest.txt
	@echo "Main-Class: CorrelateGlycans" >> manifest.txt
	@echo "" >> manifest.txt
	jar cmvf manifest.txt CorrelateGlycans.jar $(classes) 
